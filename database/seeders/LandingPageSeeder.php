<?php

namespace Database\Seeders;

use App\Models\PageSection;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'amon@teslatech.my.id'],
            [
                'name' => 'Admin 3R',
                'password' => Hash::make('gantengmax'),
                'role' => User::ROLE_SUPERADMIN,
            ]
        );

        SiteSetting::setMany([
            'company_name' => '3R Solusi Media',
            'tagline' => 'Koneksi Rumah yang Stabil & Profesional',
            'phone' => '0812-3456-7890',
            'whatsapp' => '6281234567890',
            'email' => 'halo@3rsolusimedia.id',
            'address' => 'Jl. Teknologi No. 3R, Indonesia',
            'operating_hours' => 'Senin – Sabtu, 08.00 – 18.00',
            'instagram' => 'https://instagram.com/3rsolusimedia',
            'facebook' => 'https://facebook.com/3rsolusimedia',
            'seo_title' => '3R Solusi Media — Pemasangan WiFi Rumahan Profesional',
            'seo_description' => 'Jasa pemasangan WiFi rumahan cepat, rapi, dan stabil. Survey lokasi, instalasi perangkat, hingga after-sales support dari teknisi berpengalaman.',
            ...AppSettings::DEFAULTS,
        ]);

        $sections = [
            [
                'key' => 'hero',
                'label' => 'Hero',
                'title' => 'Internet Rumah Tanpa Drama',
                'subtitle' => '3R Solusi Media',
                'body' => 'Pemasangan WiFi rumahan yang rapi, cepat, dan dikonfigurasi agar sinyal merata di seluruh ruangan.',
                'content' => [
                    'badge' => 'Instalasi WiFi Rumahan',
                    'secondary_cta_label' => 'Lihat Paket',
                    'secondary_cta_url' => '#layanan',
                ],
                'image' => '/images/hero/wifi-living.jpg',
                'image_secondary' => '/images/hero/install-tech.jpg',
                'cta_label' => 'Konsultasi Gratis',
                'cta_url' => '#kontak',
                'is_visible' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'services',
                'label' => 'Layanan',
                'title' => 'Paket layanan yang siap dipasang',
                'subtitle' => 'Layanan',
                'body' => 'Dari rumah kecil hingga hunian bertingkat, kami sesuaikan perangkat dan layout sinyal dengan kebutuhan Anda.',
                'content' => [
                    'items' => [
                        [
                            'icon' => 'wifi',
                            'title' => 'Instalasi WiFi Rumahan',
                            'description' => 'Pemasangan router dan akses poin dengan routing kabel rapi serta konfigurasi keamanan dasar.',
                        ],
                        [
                            'icon' => 'signal',
                            'title' => 'Optimasi Sinyal',
                            'description' => 'Survey dead-zone, penempatan perangkat strategis, dan tuning agar coverage merata.',
                        ],
                        [
                            'icon' => 'router',
                            'title' => 'Upgrade & Mesh Network',
                            'description' => 'Perluasan jaringan mesh untuk rumah bertingkat tanpa putus saat berpindah ruangan.',
                        ],
                        [
                            'icon' => 'shield',
                            'title' => 'Maintenance Berkala',
                            'description' => 'Pengecekan performa, update firmware, dan perbaikan gangguan koneksi rumah Anda.',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'about',
                'label' => 'Tentang Kami',
                'title' => 'Solusi konektivitas rumah yang mengutamakan kualitas kerja',
                'subtitle' => 'Tentang 3R',
                'body' => '3R Solusi Media fokus pada jasa pemasangan dan optimasi WiFi rumahan. Kami menggabungkan standar teknis yang rapi dengan komunikasi yang jelas, sehingga pelanggan paham apa yang dipasang dan mengapa.',
                'content' => [
                    'stats' => [
                        ['value' => '500+', 'label' => 'Rumah terpasang'],
                        ['value' => '98%', 'label' => 'Kepuasan klien'],
                        ['value' => '24 jam', 'label' => 'Respon support'],
                    ],
                ],
                'image' => '/images/hero/install-tech.jpg',
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'benefits',
                'label' => 'Keunggulan',
                'title' => 'Mengapa rumah Anda lebih baik bersama kami',
                'subtitle' => 'Keunggulan',
                'body' => 'Setiap pemasangan dirancang agar stabil jangka panjang — bukan sekadar “nyala dulu”.',
                'content' => [
                    'items' => [
                        [
                            'icon' => 'zap',
                            'title' => 'Instalasi cepat & rapi',
                            'description' => 'Jadwal jelas, kerja bersih, dan dokumentasi singkat setelah selesai.',
                        ],
                        [
                            'icon' => 'radar',
                            'title' => 'Survey sinyal menyeluruh',
                            'description' => 'Kami ukur titik lemah sebelum menentukan posisi perangkat.',
                        ],
                        [
                            'icon' => 'lock',
                            'title' => 'Keamanan jaringan',
                            'description' => 'Password kuat, guest network opsional, dan konfigurasi aman untuk keluarga.',
                        ],
                        [
                            'icon' => 'headphones',
                            'title' => 'After-sales responsif',
                            'description' => 'Bantuan remote atau kunjungan ulang bila terjadi gangguan.',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'process',
                'label' => 'Proses Kerja',
                'title' => 'Empat langkah menuju WiFi rumah yang stabil',
                'subtitle' => 'Cara Kerja',
                'body' => 'Proses transparan dari konsultasi hingga serah terima.',
                'content' => [
                    'steps' => [
                        [
                            'step' => '01',
                            'title' => 'Konsultasi & survey',
                            'description' => 'Kami dengarkan kebutuhan, denah rumah, dan kendala sinyal yang ada.',
                        ],
                        [
                            'step' => '02',
                            'title' => 'Rekomendasi perangkat',
                            'description' => 'Paket router/mesh disesuaikan dengan luas area dan jumlah perangkat.',
                        ],
                        [
                            'step' => '03',
                            'title' => 'Instalasi profesional',
                            'description' => 'Pemasangan, kabel rapi, dan konfigurasi SSID serta keamanan.',
                        ],
                        [
                            'step' => '04',
                            'title' => 'Uji & serah terima',
                            'description' => 'Speed test tiap zona, panduan singkat, dan kontak support.',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 5,
            ],
            [
                'key' => 'pricing',
                'label' => 'Harga & Langganan',
                'title' => 'Paket WiFi rumahan',
                'subtitle' => 'Harga Paket',
                'body' => 'Pilih yang pas buat rumah kamu. Harga jelas, pasang bisa dibantu, dan kalau bingung bisa tanya dulu.',
                'content' => [
                    'note' => 'Harga bisa beda tergantung area. Mau tanya dulu juga boleh, gratis.',
                    'plans' => [
                        [
                            'name' => 'Hemat',
                            'badge' => null,
                            'featured' => false,
                            'description' => 'Cukup buat chat, sosmed, dan nonton santai di rumah.',
                            'price' => 'Rp 120rb',
                            'period' => 'per bulan',
                            'features' => [
                                'Cocok buat rumah kecil',
                                'Bisa dipakai beberapa HP',
                                'Pasang dibantu teknisi',
                                'Kalau ada masalah bisa chat',
                            ],
                            'cta_label' => 'Ambil paket ini',
                            'cta_url' => 'whatsapp',
                        ],
                        [
                            'name' => 'Keluarga',
                            'badge' => 'Paling banyak diambil',
                            'featured' => true,
                            'description' => 'Buat keluarga yang sering online bareng — kerja, sekolah, nonton.',
                            'price' => 'Rp 150rb',
                            'period' => 'per bulan',
                            'features' => [
                                'Lebih kencang dari paket Hemat',
                                'Bisa dipakai banyak perangkat',
                                'Sinyal lebih stabil di rumah',
                                'Pasang dibantu teknisi',
                                'Bantuan lebih cepat kalau ada gangguan',
                            ],
                            'cta_label' => 'Ambil paket ini',
                            'cta_url' => 'whatsapp',
                        ],
                        [
                            'name' => 'Plus',
                            'badge' => 'Lebih kencang',
                            'featured' => false,
                            'description' => 'Buat yang butuh internet lebih kencang tiap hari.',
                            'price' => 'Rp 250rb',
                            'period' => 'per bulan',
                            'features' => [
                                'Paling kencang di antara paket ini',
                                'Nyaman buat nonton & meeting',
                                'Cocok buat rumah yang ramai online',
                                'Pasang dibantu teknisi',
                                'Prioritas bantuan kalau ada gangguan',
                            ],
                            'cta_label' => 'Ambil paket ini',
                            'cta_url' => 'whatsapp',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 6,
            ],
            [
                'key' => 'testimonials',
                'label' => 'Testimoni',
                'title' => 'Yang dikatakan pelanggan kami',
                'subtitle' => 'Testimoni',
                'body' => 'Pengalaman nyata dari pemilik rumah yang sudah menggunakan jasa kami.',
                'content' => [
                    'items' => [
                        [
                            'name' => 'Rina Wulandari',
                            'role' => 'Pemilik rumah, Bandung',
                            'quote' => 'Sinyal di lantai dua akhirnya stabil. Teknisi menjelaskan tiap langkah dengan sabar.',
                        ],
                        [
                            'name' => 'Andi Pratama',
                            'role' => 'Work from home',
                            'quote' => 'Meeting video tidak putus-putus lagi. Instalasi rapi dan tepat waktu.',
                        ],
                        [
                            'name' => 'Sinta Maharani',
                            'role' => 'Ibu rumah tangga',
                            'quote' => 'Anak-anak bisa belajar online di kamar masing-masing tanpa berebut sinyal.',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 7,
            ],
            [
                'key' => 'cta',
                'label' => 'Call to Action',
                'title' => 'Siap pasang WiFi rumah yang lebih stabil?',
                'subtitle' => 'Mulai Hari Ini',
                'body' => 'Ceritakan denah rumah Anda. Kami bantu rekomendasikan perangkat dan jadwal survey.',
                'content' => [],
                'image' => '/images/hero/wifi-living.jpg',
                'image_secondary' => null,
                'cta_label' => 'Chat WhatsApp',
                'cta_url' => 'whatsapp',
                'is_visible' => true,
                'sort_order' => 8,
            ],
            [
                'key' => 'contact',
                'label' => 'Kontak',
                'title' => 'Hubungi tim kami',
                'subtitle' => 'Kontak',
                'body' => 'Tim kami siap membantu konsultasi pemasangan, perbaikan, maupun upgrade jaringan rumah Anda.',
                'content' => [
                    'form_note' => 'Atau kirim pesan singkat — kami balas secepatnya di jam operasional.',
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => 'Kirim via WhatsApp',
                'cta_url' => 'whatsapp',
                'is_visible' => true,
                'sort_order' => 9,
            ],
            [
                'key' => 'footer',
                'label' => 'Footer',
                'title' => '3R Solusi Media',
                'subtitle' => null,
                'body' => 'Mitra pemasangan WiFi rumahan untuk koneksi yang rapi, aman, dan andal.',
                'content' => [
                    'links' => [
                        ['label' => 'Layanan', 'url' => '#layanan'],
                        ['label' => 'Harga', 'url' => '#harga'],
                        ['label' => 'Tentang', 'url' => '#tentang'],
                        ['label' => 'Proses', 'url' => '#proses'],
                        ['label' => 'Kontak', 'url' => '#kontak'],
                    ],
                    'legal_links' => [
                        ['label' => 'Terms of Service', 'url' => '/terms-of-service'],
                    ],
                    'copyright' => '© {year} 3R Solusi Media. Semua hak dilindungi.',
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 10,
            ],
            [
                'key' => 'terms',
                'label' => 'Terms of Service',
                'title' => 'Terms of Service',
                'subtitle' => 'Legal',
                'body' => 'Dengan menggunakan layanan 3R Solusi Media, Anda menyetujui ketentuan berikut. Harap baca dengan saksama sebelum memesan jasa pemasangan atau layanan terkait.',
                'content' => [
                    'updated_at' => '31 Juli 2026',
                    'paragraphs' => [
                        [
                            'heading' => '1. Layanan',
                            'body' => "3R Solusi Media menyediakan jasa konsultasi, pemasangan, optimasi, dan perawatan jaringan WiFi rumahan.\nRuang lingkup pekerjaan mengikuti kesepakatan yang dikomunikasikan sebelum instalasi dimulai.",
                        ],
                        [
                            'heading' => '2. Pemesanan & Jadwal',
                            'body' => "Pemesanan dapat dilakukan melalui WhatsApp, telepon, atau formulir kontak di situs.\nJadwal survey dan instalasi bersifat kesepakatan bersama dan dapat berubah jika ada kondisi teknis atau force majeure.",
                        ],
                        [
                            'heading' => '3. Pembayaran',
                            'body' => 'Biaya layanan diinformasikan sebelum pekerjaan dimulai. Pembayaran mengikuti skema yang disepakati (sebagian di muka, pelunasan setelah serah terima, atau sesuai penawaran tertulis).',
                        ],
                        [
                            'heading' => '4. Tanggung Jawab Pelanggan',
                            'body' => 'Pelanggan wajib memberikan akses lokasi yang aman, informasi denah/kebutuhan yang akurat, serta memastikan perangkat milik pelanggan (jika ada) dalam kondisi layak digunakan.',
                        ],
                        [
                            'heading' => '5. Garansi & After-Sales',
                            'body' => 'Garansi pekerjaan mengikuti ketentuan yang disampaikan saat serah terima. Gangguan di luar lingkup instalasi (misalnya gangguan ISP, listrik, atau kerusakan perangkat pihak ketiga) dapat dikenakan biaya tambahan.',
                        ],
                        [
                            'heading' => '6. Privasi Data',
                            'body' => 'Data kontak dan informasi teknis yang Anda berikan digunakan hanya untuk keperluan layanan, komunikasi, dan peningkatan kualitas kerja. Kami tidak menjual data pelanggan kepada pihak ketiga yang tidak relevan.',
                        ],
                        [
                            'heading' => '7. Perubahan Ketentuan',
                            'body' => 'Kami dapat memperbarui Terms of Service sewaktu-waktu. Versi terbaru selalu tersedia di halaman ini. Penggunaan layanan setelah pembaruan dianggap sebagai penerimaan atas ketentuan baru.',
                        ],
                        [
                            'heading' => '8. Kontak',
                            'body' => 'Untuk pertanyaan terkait ketentuan ini, hubungi kami melalui email atau WhatsApp yang tertera di situs resmi 3R Solusi Media.',
                        ],
                    ],
                ],
                'image' => null,
                'image_secondary' => null,
                'cta_label' => null,
                'cta_url' => null,
                'is_visible' => true,
                'sort_order' => 11,
            ],
        ];

        foreach ($sections as $section) {
            PageSection::updateOrCreate(
                ['key' => $section['key']],
                $section
            );
        }
    }
}
