@extends('layouts.app')

@section('title', 'Metode Pembayaran Dipilih')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-12">
    <div class="bg-white p-6 rounded-lg border border-gray-200">
        <h1 class="text-xl font-semibold">Metode pembayaran berhasil dipilih.</h1>
        <dl class="mt-4 space-y-2 text-sm text-gray-700">
            <div><dt class="font-semibold">Metode</dt><dd>{{ $paymentMethod }}</dd></div>
            <div><dt class="font-semibold">Nominal Pembayaran</dt><dd>Rp {{ number_format($paymentAmount, 0, ',', '.') }}</dd></div>
            <div><dt class="font-semibold">Draft</dt><dd>{{ $draftCode }}</dd></div>
        </dl>
        @if($snapToken)
            <button id="pay-button" type="button" class="mt-6 rounded-md bg-amber-600 px-5 py-2 text-white">Bayar dengan Midtrans</button>
            <p id="payment-status" class="mt-4 text-sm text-gray-600" role="status"></p>
            <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $midtransClientKey }}"></script>
            <script>
                const paymentStatus = document.getElementById('payment-status');
                const verifyUrl = @json(route('reservasi.payment.verify_local'));
                const csrfToken = @json(csrf_token());
                const orderId = @json($orderId);
                const draftCode = @json($draftCode);

                function verifyPayment(attempt) {
                    paymentStatus.textContent = 'Memeriksa status pembayaran...';

                    fetch(verifyUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ order_id: orderId, draft_code: draftCode })
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                return { ok: response.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            if (result.data.status === 'success') {
                                window.location.href = result.data.redirect_url;
                                return;
                            }

                            if (result.data.status === 'pending' && attempt < 5) {
                                paymentStatus.textContent = 'Pembayaran masih diproses. Pengecekan ulang...';
                                window.setTimeout(function () {
                                    verifyPayment(attempt + 1);
                                }, 2000);
                                return;
                            }

                            if (result.data.status === 'pending') {
                                paymentStatus.textContent = 'Pembayaran sedang diproses. Silakan cek status reservasi beberapa saat lagi.';
                                return;
                            }

                            paymentStatus.textContent = result.data.message || 'Pembayaran gagal diverifikasi.';
                        })
                        .catch(function () {
                            paymentStatus.textContent = 'Terjadi kesalahan saat memeriksa pembayaran.';
                        });
                }

                document.getElementById('pay-button').addEventListener('click', function () {
                    window.snap.pay(@json($snapToken), {
                        onSuccess: function () {
                            verifyPayment(1);
                        }
                    });
                });
            </script>
        @endif
    </div>
</div>

@endsection
