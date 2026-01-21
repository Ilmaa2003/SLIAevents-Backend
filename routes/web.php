<?php

use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;

// Existing routes...

/* ===== Temporary PDF Test Route ===== */
Route::get('/test-pdf', function() {
    $pdf = Pdf::loadView('pdf.inauguration-pass', [
        'membership' => 'M123',
        'name' => 'John Doe',
        'email' => 'ilmaa200308@gmail.com',
        'qr' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA
                  AAAFCAYAAACNbyblAAAAHElEQVQI12P4
                  //8/w38GIAXDIBKE0DHxgljNBAAO
                  9TXL0Y4OHwAAAABJRU5ErkJggg==' // sample QR
    ]);
    return $pdf->download('test.pdf');
});
