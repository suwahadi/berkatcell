<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'tentang-kami' => [
                'title' => 'Tentang Kami',
                'content' => <<<'HTML'
                    <p>Berkat Cell adalah penyedia layanan jual beli handphone terpercaya yang menawarkan berbagai pilihan gadget berkualitas dengan harga terbaik. Kami mengutamakan pelayanan cepat, transaksi aman, serta kemudahan berbelanja untuk memenuhi kebutuhan Anda.</p>
                    <p>Kami menyediakan beragam merek populer seperti iPhone, Samsung, Infinix, Oppo, dan Vivo dengan varian warna serta kapasitas RAM/penyimpanan yang lengkap. Setiap unit dapat Anda cek ketersediaan stok dan spesifikasinya secara langsung di website.</p>
                    <ul>
                        <li>Pelayanan cepat dan ramah</li>
                        <li>Transaksi aman melalui payment gateway resmi</li>
                        <li>Pilihan merek dan varian yang lengkap dengan harga kompetitif</li>
                    </ul>
                    <p>Toko kami berlokasi di Jl. Rw. Bebek II No.08 18, RT.4/RW.11, Penjaringan, Jakarta Utara. Jam operasional setiap hari, pukul 09.00–21.00 WIB.</p>
                    HTML,
            ],
            'kebijakan-pengiriman' => [
                'title' => 'Kebijakan Pengiriman',
                'content' => <<<'HTML'
                    <p>Berkat Cell melayani pengiriman ke seluruh Indonesia menggunakan jasa ekspedisi tepercaya seperti <strong>JNE</strong> dan <strong>J&amp;T</strong>, serta kurir terkemuka lainnya.</p>
                    <ul>
                        <li>Pesanan diproses pada hari kerja setelah pembayaran terverifikasi.</li>
                        <li>Ongkos kirim dihitung otomatis berdasarkan alamat tujuan dan berat paket saat checkout.</li>
                        <li>Setiap unit dikemas dengan aman untuk menjaga kondisi barang selama pengiriman.</li>
                        <li>Nomor resi akan kami kirimkan melalui email setelah paket diserahkan ke kurir.</li>
                    </ul>
                    <p>Pastikan alamat dan nomor kontak yang Anda masukkan sudah benar agar pengiriman berjalan lancar.</p>
                    HTML,
            ],
            'faq' => [
                'title' => 'Pertanyaan Umum (FAQ)',
                'content' => <<<'HTML'
                    <p><strong>Apakah unit yang dijual bergaransi?</strong><br>Sebagian besar unit merupakan unit second berkualitas dengan kondisi sesuai foto. Silakan hubungi kami melalui WhatsApp untuk detail kondisi dan garansi tiap unit.</p>
                    <p><strong>Bagaimana cara mengetahui stok dan varian yang tersedia?</strong><br>Ketersediaan stok, warna, serta kapasitas RAM/penyimpanan dapat Anda lihat langsung pada halaman masing-masing produk.</p>
                    <p><strong>Metode pembayaran apa saja yang tersedia?</strong><br>Pembayaran diproses secara aman melalui payment gateway resmi (Midtrans) yang mendukung transfer bank, e-wallet, dan kartu kredit.</p>
                    <p><strong>Apakah bisa kirim ke luar kota?</strong><br>Bisa. Kami melayani pengiriman ke seluruh Indonesia melalui JNE, J&amp;T, dan kurir lainnya.</p>
                    <p><strong>Bagaimana jika ingin bertanya lebih lanjut?</strong><br>Hubungi kami melalui WhatsApp di 0877-7600-6060 pada jam operasional setiap hari, 09.00–21.00 WIB.</p>
                    HTML,
            ],
        ];

        foreach ($pages as $slug => $data) {
            Page::query()->updateOrCreate(
                ['slug' => $slug],
                [...$data, 'is_active' => true],
            );
        }
    }
}
