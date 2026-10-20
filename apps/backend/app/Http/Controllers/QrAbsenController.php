<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\QrAbsen;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrAbsenController extends Controller
{
    private function generateCode(): string
    {
        do {
            $code = Str::random(32);
        } while (QrAbsen::where('qr_checkin', $code)->orWhere('qr_checkout', $code)->exists());

        return $code;
    }

    private function qrImage(?string $code): ?string
    {
        if (! $code) {
            return null;
        }
        $result = (new PngWriter)->write(QrCode::create($code)->setSize(240)->setMargin(24));

        return base64_encode($result->getString());
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $filters = ['month' => $filters['month'] ?? '', 'date' => $filters['date'] ?? ''];
        $qrAbsen = QrAbsen::query()
            ->when($filters['month'] !== '', function ($q) use ($filters) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month']);
                $q->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
            })
            ->when($filters['date'] !== '', fn ($q) => $q->where('date', $filters['date']))
            ->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pages.qr_absen.index', compact('qrAbsen', 'filters'));
    }

    public function show($id)
    {
        $qrAbsen = QrAbsen::findOrFail($id);

        return view('pages.qr_absen.show', [
            'qrAbsen' => $qrAbsen, 'company' => Company::findOrFail(auth()->user()->company_id), 'qrCodeCheckin' => $this->qrImage($qrAbsen->qr_checkin),
            'qrCodeCheckout' => $this->qrImage($qrAbsen->qr_checkout),
        ]);
    }

    public function create()
    {
        return view('pages.qr_absen.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month']);
        $changed = DB::transaction(function () use ($request, $month) {
            Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            $changed = 0;
            for ($day = 1; $day <= $month->daysInMonth; $day++) {
                $date = $month->setDay($day)->toDateString();
                $qr = QrAbsen::firstOrNew(['date' => $date]);
                if ($qr->qr_checkin && $qr->qr_checkout) {
                    continue;
                }
                if (! $qr->qr_checkin) {
                    do {
                        $qr->qr_checkin = $this->generateCode();
                    } while ($qr->qr_checkin === $qr->qr_checkout);
                }
                if (! $qr->qr_checkout) {
                    do {
                        $qr->qr_checkout = $this->generateCode();
                    } while ($qr->qr_checkout === $qr->qr_checkin);
                }
                $qr->save();
                $changed++;
            }

            return $changed;
        });

        return redirect()->route('qr_absens.index', ['month' => $data['month']])->with('success',
            $changed.' hari QR ditambahkan atau dilengkapi. QR yang sudah lengkap tetap digunakan.');
    }

    public function downloadPDF($id)
    {
        $qrAbsen = QrAbsen::findOrFail($id);
        abort_unless($qrAbsen->qr_checkin && $qrAbsen->qr_checkout, 422, 'Pasangan QR belum lengkap. Lengkapi melalui pembuatan QR bulanan.');

        return Pdf::loadView('pages.pdf.qr_absen', [
            'qrAbsen' => $qrAbsen, 'company' => Company::findOrFail(auth()->user()->company_id),
            'qrCodeCheckin' => $this->qrImage($qrAbsen->qr_checkin), 'qrCodeCheckout' => $this->qrImage($qrAbsen->qr_checkout),
        ])->setPaper('a4')->download('qr_absen_'.$qrAbsen->date.'.pdf');
    }
}
