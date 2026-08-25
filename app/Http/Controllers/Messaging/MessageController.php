<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\PublishNoticeRequest;
use App\Models\CommunicationDelivery;
use App\Models\Estate;
use App\Models\MessageThread;
use App\Models\Notice;
use App\Models\Tenant;
use App\Notifications\TenantEventNotification;
use App\Services\Communication\TenantNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(): Response
    {
        $notices = Notice::query()
            ->with(['creator', 'estate', 'tenant'])
            ->latest('published_at')
            ->limit(30)
            ->get();
        $threads = MessageThread::query()
            ->with(['tenant', 'messages.sender'])
            ->latest('last_message_at')
            ->get();

        return Inertia::render('messages/Index', [
            'summary' => [
                'published' => Notice::query()->count(),
                'open_threads' => $threads->where('status', 'open')->count(),
                'unread_tenant_messages' => $threads->flatMap->messages->where('sender_role', 'tenant')->whereNull('read_at')->count(),
                'delivery_failures' => CommunicationDelivery::query()->where('status', 'failed')->count(),
            ],
            'notices' => $notices->map(fn (Notice $notice): array => [
                'id' => $notice->id,
                'subject' => $notice->subject,
                'body' => $notice->body,
                'severity' => $notice->severity,
                'audience' => match ($notice->audience_type) {
                    'estate' => $notice->estate_id === null ? 'Estate' : $notice->estate->name,
                    'tenant' => $notice->tenant_id === null ? 'Tenant' : $notice->tenant->full_name,
                    default => 'All tenants',
                },
                'channels' => $notice->channels,
                'recipient_count' => $notice->recipient_count,
                'creator' => $notice->created_by === null ? null : $notice->creator->name,
                'published_at' => $notice->published_at->format('d M Y, g:i A'),
            ])->values(),
            'threads' => $threads->map(fn (MessageThread $thread): array => $this->threadData($thread))->values(),
            'tenants' => Tenant::query()->orderBy('last_name')->orderBy('first_name')->get()->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'full_name' => $tenant->full_name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
            ]),
            'estates' => Estate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function publish(PublishNoticeRequest $request, TenantNotifier $notifier): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $recipients = $this->recipients($data);

        if ($recipients->isEmpty()) {
            return back()->withErrors(['audience_type' => 'No tenants match this audience.']);
        }

        $notice = DB::transaction(function () use ($data, $recipients, $request): Notice {
            $notice = Notice::query()->create([
                ...$data,
                'estate_id' => $data['audience_type'] === 'estate' ? $data['estate_id'] : null,
                'tenant_id' => $data['audience_type'] === 'tenant' ? $data['tenant_id'] : null,
                'recipient_count' => $recipients->count(),
                'created_by' => $request->user()->id,
                'published_at' => now(),
            ]);

            $notice->recipients()->createMany($recipients->map(fn (Tenant $tenant): array => [
                'tenant_id' => $tenant->id,
            ])->all());

            return $notice;
        });

        foreach ($recipients as $tenant) {
            $notifier->send($tenant, new TenantEventNotification(
                eventType: 'management_notice',
                title: $notice->subject,
                message: $notice->body,
                reference: 'NOTICE-'.$notice->id,
                actionUrl: route('tenant.messages'),
                actionLabel: 'Read management notice',
                severity: $notice->severity === 'urgent' ? 'warning' : $notice->severity,
            ), $notice->channels);
        }

        return to_route('messages.index')->with('success', "Notice published to {$recipients->count()} tenants.");
    }

    public function reply(Request $request, MessageThread $messageThread, TenantNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'status' => ['required', 'in:open,closed'],
        ]);
        $body = trim((string) $validated['body']);

        DB::transaction(function () use ($messageThread, $request, $body, $validated): void {
            $messageThread->messages()->create([
                'sender_user_id' => $request->user()->id,
                'sender_role' => 'management',
                'body' => $body,
            ]);
            $messageThread->messages()->where('sender_role', 'tenant')->whereNull('read_at')->update(['read_at' => now()]);
            $messageThread->update([
                'status' => $validated['status'],
                'last_message_at' => now(),
            ]);
        });

        $tenant = $messageThread->tenant;
        $channels = array_values(array_unique(['database', 'email', $tenant->preferred_channel]));
        $notifier->send($tenant, new TenantEventNotification(
            eventType: 'management_reply',
            title: 'Management replied · '.$messageThread->subject,
            message: $body,
            reference: 'MSG-'.$messageThread->id,
            actionUrl: route('tenant.messages'),
            actionLabel: 'Read and reply',
        ), $channels);

        return to_route('messages.index')->with('success', 'Reply sent to the tenant dashboard and configured channels.');
    }

    public function read(MessageThread $messageThread): RedirectResponse
    {
        $messageThread->messages()
            ->where('sender_role', 'tenant')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Tenant>
     */
    private function recipients(array $data): Collection
    {
        $query = Tenant::query();

        if ($data['audience_type'] === 'tenant') {
            $query->whereKey((int) $data['tenant_id']);
        }

        if ($data['audience_type'] === 'estate') {
            $query->whereHas('activeLease.unit.section', fn ($query) => $query->where('estate_id', (int) $data['estate_id']));
        }

        return $query->orderBy('last_name')->orderBy('first_name')->get();
    }

    /** @return array<string, mixed> */
    private function threadData(MessageThread $thread): array
    {
        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'category' => $thread->category,
            'status' => $thread->status,
            'tenant' => ['id' => $thread->tenant->id, 'full_name' => $thread->tenant->full_name],
            'last_message_at' => $thread->last_message_at->format('d M Y, g:i A'),
            'messages' => $thread->messages->map(fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'sender' => $message->sender_user_id === null ? ($message->sender_role === 'tenant' ? $thread->tenant->full_name : 'Management') : $message->sender->name,
                'sender_role' => $message->sender_role,
                'read' => $message->read_at !== null,
                'created_at' => $message->created_at?->format('d M Y, g:i A'),
            ])->values(),
        ];
    }
}
