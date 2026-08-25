<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\NoticeRecipient;
use App\Notifications\ManagementEventNotification;
use App\Services\Communication\ManagementNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()?->tenantProfile;

        if ($tenant === null) {
            return Inertia::render('tenant/Messages', ['notices' => [], 'threads' => []]);
        }

        NoticeRecipient::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $noticeRecipients = NoticeRecipient::query()
            ->with('notice.creator')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
        $threads = MessageThread::query()
            ->with('messages.sender')
            ->where('tenant_id', $tenant->id)
            ->latest('last_message_at')
            ->get();

        $threads->flatMap->messages
            ->where('sender_role', 'management')
            ->whereNull('read_at')
            ->each->update(['read_at' => now()]);

        return Inertia::render('tenant/Messages', [
            'notices' => $noticeRecipients->map(fn (NoticeRecipient $recipient): array => [
                'id' => $recipient->notice->id,
                'subject' => $recipient->notice->subject,
                'body' => $recipient->notice->body,
                'severity' => $recipient->notice->severity,
                'from' => $recipient->notice->created_by === null ? 'K&R Estates Management' : $recipient->notice->creator->name,
                'published_at' => $recipient->notice->published_at->format('d M Y, g:i A'),
                'read' => true,
            ])->values(),
            'threads' => $threads->map(fn (MessageThread $thread): array => $this->threadData($thread))->values(),
        ]);
    }

    public function store(Request $request, ManagementNotifier $notifier): RedirectResponse
    {
        $tenant = $request->user()?->tenantProfile;
        abort_if($tenant === null, 403);
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', 'in:general,rent,security,neighbour,other'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $thread = DB::transaction(function () use ($request, $tenant, $validated): MessageThread {
            $thread = MessageThread::query()->create([
                'tenant_id' => $tenant->id,
                'subject' => trim((string) $validated['subject']),
                'category' => $validated['category'],
                'status' => 'open',
                'last_message_at' => now(),
                'created_by' => $request->user()->id,
            ]);
            $thread->messages()->create([
                'sender_user_id' => $request->user()->id,
                'sender_role' => 'tenant',
                'body' => trim((string) $validated['body']),
            ]);

            return $thread;
        });

        $notifier->send(new ManagementEventNotification(
            eventType: 'tenant_message',
            title: 'New tenant message',
            message: "{$tenant->full_name}: {$thread->subject}",
            reference: 'MSG-'.$thread->id,
            actionUrl: route('messages.index'),
        ));

        return to_route('tenant.messages')->with('success', 'Your message was sent to management.');
    }

    public function reply(Request $request, MessageThread $messageThread, ManagementNotifier $notifier): RedirectResponse
    {
        $tenant = $request->user()?->tenantProfile;
        abort_unless($tenant !== null && $messageThread->tenant_id === $tenant->id, 403);
        abort_if($messageThread->status === 'closed', 422, 'This conversation is closed.');
        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $body = trim((string) $validated['body']);

        DB::transaction(function () use ($messageThread, $request, $body): void {
            $messageThread->messages()->create([
                'sender_user_id' => $request->user()->id,
                'sender_role' => 'tenant',
                'body' => $body,
            ]);
            $messageThread->update(['last_message_at' => now()]);
        });

        $notifier->send(new ManagementEventNotification(
            eventType: 'tenant_message_reply',
            title: 'Tenant replied · '.$messageThread->subject,
            message: $body,
            reference: 'MSG-'.$messageThread->id,
            actionUrl: route('messages.index'),
        ));

        return to_route('tenant.messages')->with('success', 'Reply sent to management.');
    }

    /** @return array<string, mixed> */
    private function threadData(MessageThread $thread): array
    {
        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'category' => $thread->category,
            'status' => $thread->status,
            'last_message_at' => $thread->last_message_at->format('d M Y, g:i A'),
            'messages' => $thread->messages->map(fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'sender' => $message->sender_user_id === null ? ($message->sender_role === 'tenant' ? 'You' : 'Management') : $message->sender->name,
                'sender_role' => $message->sender_role,
                'created_at' => $message->created_at?->format('d M Y, g:i A'),
            ])->values(),
        ];
    }
}
