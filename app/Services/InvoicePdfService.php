<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    /**
     * Render the invoice as a PDF and return the binary.
     * The logo is embedded as a data URI so the PDF renderer never needs a file path.
     */
    public function render(Invoice $invoice, CompanySetting $company): string
    {
        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'logo' => $this->logoDataUri($company),
        ])
            ->setPaper('a4')
            ->output();
    }

    private function logoDataUri(CompanySetting $company): ?string
    {
        if (! $company->logo_path || ! Storage::disk('public')->exists($company->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($company->logo_path);
        $data = base64_encode(Storage::disk('public')->get($company->logo_path));

        return "data:{$mime};base64,{$data}";
    }
}
