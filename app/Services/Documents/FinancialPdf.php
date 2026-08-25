<?php

namespace App\Services\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class FinancialPdf
{
    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function download(string $view, array $data, string $filename): Response
    {
        return response($this->render($view, $data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
