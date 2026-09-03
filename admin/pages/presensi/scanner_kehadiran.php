<?php
// (File: admin/pages/presensi/scanner_kehadiran.php)

$sesi_list = [];
date_default_timezone_set('Asia/Jakarta');
$result_sesi_list = $conn->query("SELECT id, nama_sesi, tanggal_sesi, waktu_sesi FROM sesi_presensi ORDER BY nama_sesi");
if ($result_sesi_list) {
    while ($row = $result_sesi_list->fetch_assoc()) {
        $waktu_clean = str_replace('.', ':', $row['waktu_sesi']);
        $waktu_parts = explode('-', $waktu_clean);
        $start_time = trim($waktu_parts[0]);
        $start_time = preg_replace('/[^0-9:]/', '', $start_time);
        
        $row['start_timestamp'] = 0;
        if (strlen($start_time) >= 4) {
            $start_datetime_str = $row['tanggal_sesi'] . ' ' . $start_time . ':00';
            $row['start_timestamp'] = strtotime($start_datetime_str);
            $row['start_datetime_str'] = date('d M Y, H:i', $row['start_timestamp']);
        }
        $sesi_list[] = $row;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
@keyframes scanCardIn {
    from { opacity: 0; transform: scale(0.75); }
    to   { opacity: 1; transform: scale(1); }
}
@keyframes drawStroke {
    to { stroke-dashoffset: 0; }
}
@keyframes pulseRing {
    0%   { transform: scale(1);   opacity: 0.6; }
    100% { transform: scale(1.5); opacity: 0; }
}
.scan-result-card { animation: scanCardIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both; }
.anim-circle      { stroke-dasharray: 166; stroke-dashoffset: 166; animation: drawStroke 0.5s ease-out 0.05s forwards; }
.anim-check       { stroke-dasharray: 55;  stroke-dashoffset: 55;  animation: drawStroke 0.35s ease-out 0.45s forwards; }
.anim-cross1      { stroke-dasharray: 30;  stroke-dashoffset: 30;  animation: drawStroke 0.25s ease-out 0.4s  forwards; }
.anim-cross2      { stroke-dasharray: 30;  stroke-dashoffset: 30;  animation: drawStroke 0.25s ease-out 0.58s forwards; }
.pulse-ring       { animation: pulseRing 1s ease-out infinite; }

/* Fullscreen Styles */
#scanner-fullscreen-wrapper:fullscreen {
    background-color: #0f172a;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    overflow-y: auto;
}
#scanner-fullscreen-wrapper:fullscreen .hide-in-fs {
    display: none !important;
}
.fs-only-block, .fs-only-flex {
    display: none;
}
#scanner-fullscreen-wrapper:fullscreen .fs-only-block {
    display: block !important;
}
#scanner-fullscreen-wrapper:fullscreen .fs-only-flex {
    display: flex !important;
}
#scanner-fullscreen-wrapper:fullscreen .standard-header {
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    width: 100%;
}
#scanner-fullscreen-wrapper:fullscreen .clock-widget {
    transform: scale(1.2);
    transform-origin: top center;
    margin-top: 1rem;
}
#scanner-fullscreen-wrapper:fullscreen .scanner-container {
    max-width: 600px;
    width: 100%;
}
.mirror-video video {
    transform: scaleX(-1) !important;
}
.scroll-locked, #scanner-fullscreen-wrapper.scroll-locked:fullscreen {
    overflow: hidden !important;
}
</style>

<script>
    function scannerKehadiranData() {
        return {
            sesiData: <?php echo json_encode($sesi_list); ?>,
            sesiTerpilih: '',
            sesiAktif: false,
            namaSesiTerpilih: '',
            waktuSesiTerpilih: '',
            tanggalSesiTerpilih: '',
            scanResult: { status: '', message: '', visible: false },
            html5QrCode: null,
            _dismissTimer: null,

            // --- State Kamera ---
            cameras: [],
            selectedCamera: '',
            camerasLoaded: false,
            camerasError: '',
            isMirrored: false,
            
            // --- State Scroll Lock ---
            isScrollLocked: false,
            isLockButtonVisible: false,
            _lockBtnTimer: null,

            // Lifecycle Alpine.js: dipanggil otomatis saat komponen init
            init() {
                this.loadCameras();

                // Saat kamera berganti & scanner sedang aktif, restart scanner
                this.$watch('selectedCamera', (newVal, oldVal) => {
                    if (oldVal && newVal && newVal !== oldVal && this.sesiAktif) {
                        this.restartWithNewCamera();
                    }
                });

                document.addEventListener('fullscreenchange', () => {
                    if (!document.fullscreenElement && this.isScrollLocked) {
                        this.isScrollLocked = false;
                        document.body.classList.remove('scroll-locked');
                        const fsWrapper = document.getElementById('scanner-fullscreen-wrapper');
                        if (fsWrapper) fsWrapper.classList.remove('scroll-locked');
                        this.isLockButtonVisible = false;
                    }
                });
            },

            loadCameras() {
                this.camerasLoaded = false;
                this.camerasError  = '';
                Html5Qrcode.getCameras()
                    .then(devices => {
                        if (devices && devices.length > 0) {
                            this.cameras = devices;
                            // Pilih kamera belakang/environment secara default
                            const back = devices.find(d => /back|rear|environment/i.test(d.label));
                            this.selectedCamera = back ? back.id : devices[0].id;
                        } else {
                            this.camerasError = 'Tidak ada kamera yang ditemukan.';
                        }
                        this.camerasLoaded = true;
                    })
                    .catch(err => {
                        this.camerasError = 'Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.';
                        this.camerasLoaded = true;
                        console.error('Camera enumeration error:', err);
                    });
            },

            // Hentikan scanner lama, lalu mulai ulang dengan kamera baru
            restartWithNewCamera() {
                if (this.html5QrCode && this.html5QrCode.isScanning) {
                    this.html5QrCode.stop()
                        .then(() => { this.startScanner(); })
                        .catch(err => console.error('Stop error:', err));
                } else {
                    this.startScanner();
                }
            },

            mulaiSesi() {
                if (!this.sesiTerpilih || !this.selectedCamera) return;
                
                const sesi = this.sesiData.find(s => s.id == this.sesiTerpilih);
                if (sesi && sesi.start_timestamp) {
                    const now = Math.floor(Date.now() / 1000);
                    if (now < sesi.start_timestamp) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Belum Mulai',
                            text: 'Sesi ini belum dimulai. Tunggu hingga ' + sesi.start_datetime_str + ' WIB.',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Mengerti'
                        });
                        return;
                    }
                }
                
                const opt = document.querySelector(`#sesi_id option[value='${this.sesiTerpilih}']`);
                this.namaSesiTerpilih = opt ? opt.textContent : '';
                this.waktuSesiTerpilih = sesi.waktu_sesi;
                
                if(sesi.tanggal_sesi) {
                   const d = new Date(sesi.tanggal_sesi);
                   const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                   this.tanggalSesiTerpilih = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
                } else {
                   this.tanggalSesiTerpilih = '-';
                }

                this.sesiAktif = true;
                this.scanResult = { status: '', message: '', visible: false };
                this.$nextTick(() => { this.startScanner(); });
            },

            gantiSesi() {
                if (this._dismissTimer) { clearTimeout(this._dismissTimer); this._dismissTimer = null; }
                this.stopScanner();
                this.sesiAktif    = false;
                this.sesiTerpilih = '';
                this.scanResult   = { status: '', message: '', visible: false };
            },

            startScanner() {
                if (!this.selectedCamera) return;
                if (!this.html5QrCode) this.html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                this.html5QrCode.start(
                    this.selectedCamera,
                    config,
                    (decodedText) => { this.html5QrCode.pause(); this.handleScan(decodedText); },
                    () => {}
                ).catch(() => {
                    this.scanResult = {
                        status: 'error',
                        message: 'Gagal memulai kamera. Pastikan izin kamera diberikan.',
                        visible: true
                    };
                });
            },

            stopScanner() {
                if (this.html5QrCode && this.html5QrCode.isScanning) {
                    this.html5QrCode.stop().catch(err => console.error("Stop scanner error:", err));
                }
            },

            toggleFullscreen() {
                const elem = document.getElementById('scanner-fullscreen-wrapper');
                if (!document.fullscreenElement) {
                    elem.requestFullscreen().catch(err => {
                        console.error(`Error attempting to enable fullscreen: ${err.message}`);
                    });
                } else {
                    document.exitFullscreen();
                }
            },

            showLockButton() {
                if (!document.fullscreenElement) return;
                this.isLockButtonVisible = true;
                if (this._lockBtnTimer) clearTimeout(this._lockBtnTimer);
                this._lockBtnTimer = setTimeout(() => {
                    this.isLockButtonVisible = false;
                }, 3000);
            },

            toggleScrollLock() {
                this.isScrollLocked = !this.isScrollLocked;
                const fsWrapper = document.getElementById('scanner-fullscreen-wrapper');
                if (this.isScrollLocked) {
                    document.body.classList.add('scroll-locked');
                    if (fsWrapper) fsWrapper.classList.add('scroll-locked');
                } else {
                    document.body.classList.remove('scroll-locked');
                    if (fsWrapper) fsWrapper.classList.remove('scroll-locked');
                }
                this.showLockButton();
            },

            handleScan(barcode) {
                if (this._dismissTimer) { clearTimeout(this._dismissTimer); this._dismissTimer = null; }
                this.scanResult = { status: 'info', message: 'Memproses...', visible: true };

                fetch('pages/presensi/api_presensi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ barcode: barcode, sesi_id: this.sesiTerpilih })
                })
                .then(response => {
                    if (!response.ok) return response.json().then(err => { throw new Error(err.message || 'Error tidak diketahui'); });
                    return response.json();
                })
                .then(data  => { this.scanResult = { ...data, visible: true }; })
                .catch(error => { this.scanResult = { status: 'error', message: `Terjadi masalah: ${error.message}`, visible: true }; })
                .finally(() => {
                    this._dismissTimer = setTimeout(() => {
                        this.scanResult.visible = false;
                        this._dismissTimer = null;
                        if (this.html5QrCode && this.sesiAktif) this.html5QrCode.resume();
                    }, 2500);
                });
            }
        };
    }
</script>

<div x-data="scannerKehadiranData()" id="scanner-fullscreen-wrapper" class="w-full relative">

    <!-- Area Transparan untuk Memunculkan Tombol Lock -->
    <div @click="showLockButton()" 
         class="fixed top-0 right-0 w-24 h-24 z-[60] cursor-pointer"
         title="Klik di sini untuk memunculkan tombol Scroll Lock"
         style="opacity: 0;">
    </div>

    <!-- Tombol Scroll Lock Mengambang -->
    <button x-show="isLockButtonVisible" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            @click.stop="toggleScrollLock()"
            class="fixed top-6 right-6 z-[70] px-4 py-2 bg-gray-800/80 hover:bg-gray-700 text-white rounded-xl shadow-lg transition-all flex items-center gap-2 backdrop-blur-sm cursor-pointer"
            x-cloak>
        <i class="fas" :class="isScrollLocked ? 'fa-lock text-red-400' : 'fa-unlock text-green-400'"></i>
        <span class="text-sm font-semibold" x-text="isScrollLocked ? 'Scroll Locked' : 'Scroll Unlocked'"></span>
    </button>


    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 standard-header w-full">
        <div class="hide-in-fs">
            <h1 class="text-2xl font-bold text-gray-800">Scanner Kehadiran</h1>
            <p class="text-sm text-gray-500 mt-0.5">Scan QR Code peserta untuk mencatat kehadiran</p>
        </div>
        
        <!-- Judul Fullscreen (hanya tampil di FS) -->
        <div class="fs-only-block flex-1 text-center w-full mt-4">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 tracking-tight drop-shadow-md" x-text="namaSesiTerpilih"></h1>
            <div class="inline-flex items-center gap-4 px-6 py-2.5 rounded-full bg-blue-900/60 border border-blue-700/50 shadow-inner backdrop-blur-sm">
                <span class="text-lg text-blue-100 font-medium flex items-center gap-2"><i class="far fa-calendar-alt text-blue-300"></i> <span x-text="tanggalSesiTerpilih"></span></span>
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                <span class="text-lg text-blue-100 font-medium flex items-center gap-2"><i class="far fa-clock text-blue-300"></i> <span x-text="waktuSesiTerpilih"></span></span>
            </div>
        </div>

        <!-- Widget Jam Real-Time -->
        <div class="clock-widget flex-shrink-0 bg-gradient-to-br from-blue-700 to-blue-900 rounded-2xl shadow-lg p-4 text-white flex items-center gap-4 min-w-[220px]">
            <div class="text-center flex-1">
                <div id="clock-time" class="text-3xl font-bold tracking-widest tabular-nums leading-none">00:00:00</div>
                <div id="clock-date" class="text-xs text-blue-200 mt-1 font-medium">—</div>
                <div class="mt-2 inline-flex items-center gap-1.5 bg-blue-600/60 backdrop-blur-sm border border-blue-400/40 rounded-full px-3 py-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse inline-block"></span>
                    <span id="clock-timezone" class="text-xs font-semibold text-blue-100 tracking-wide">WIB</span>
                </div>
            </div>
            <div class="w-px h-14 bg-blue-500/50"></div>
            <div class="flex-shrink-0">
                <canvas id="analog-clock" width="64" height="64"></canvas>
            </div>
        </div>
    </div>

    <!-- Script Jam -->
    <script>
    (function () {
        const HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const BULAN = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        function getTimezoneLabel(offset) {
            if (offset === 420) return 'WIB';
            if (offset === 480) return 'WITA';
            if (offset === 540) return 'WIT';
            const h=Math.floor(Math.abs(offset)/60),m=Math.abs(offset)%60,sign=offset>=0?'+':'-';
            return 'UTC'+sign+String(h).padStart(2,'0')+(m?':'+String(m).padStart(2,'0'):'');
        }
        function drawAnalogClock(canvas,h,m,s) {
            const ctx=canvas.getContext('2d'),cx=canvas.width/2,cy=canvas.height/2,r=cx-3;
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.beginPath();ctx.arc(cx,cy,r,0,2*Math.PI);ctx.fillStyle='rgba(255,255,255,0.12)';ctx.fill();
            ctx.strokeStyle='rgba(255,255,255,0.4)';ctx.lineWidth=1.5;ctx.stroke();
            for(let i=0;i<12;i++){const a=(i/12)*2*Math.PI-Math.PI/2;ctx.beginPath();ctx.arc(cx+Math.cos(a)*(r-5),cy+Math.sin(a)*(r-5),i%3===0?2:1,0,2*Math.PI);ctx.fillStyle='rgba(255,255,255,0.7)';ctx.fill();}
            function drawHand(angle,length,width,color){ctx.save();ctx.translate(cx,cy);ctx.rotate(angle);ctx.beginPath();ctx.moveTo(0,length*0.2);ctx.lineTo(0,-length);ctx.strokeStyle=color;ctx.lineWidth=width;ctx.lineCap='round';ctx.stroke();ctx.restore();}
            drawHand(((h%12)/12+m/720+s/43200)*2*Math.PI,r*0.5,3,'rgba(255,255,255,0.95)');
            drawHand((m/60+s/3600)*2*Math.PI,r*0.72,2,'rgba(255,255,255,0.9)');
            drawHand((s/60)*2*Math.PI,r*0.78,1,'#fbbf24');
            ctx.beginPath();ctx.arc(cx,cy,3,0,2*Math.PI);ctx.fillStyle='#fbbf24';ctx.fill();
        }
        function updateClock(){const now=new Date(),h=now.getHours(),m=now.getMinutes(),s=now.getSeconds();document.getElementById('clock-time').textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');document.getElementById('clock-date').textContent=HARI[now.getDay()]+', '+now.getDate()+' '+BULAN[now.getMonth()]+' '+now.getFullYear();document.getElementById('clock-timezone').textContent=getTimezoneLabel(-now.getTimezoneOffset());const canvas=document.getElementById('analog-clock');if(canvas)drawAnalogClock(canvas,h,m,s);}
        updateClock();setInterval(updateClock,1000);
    })();
    </script>

    <!-- ===================================================== -->
    <!-- Tampilan Awal: Pilih Sesi + Pilih Kamera              -->
    <!-- ===================================================== -->
    <div x-show="!sesiAktif" x-transition class="mt-6">
        <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
                <i class="fas fa-camera-rotate text-white text-sm"></i>
                <span class="text-white font-semibold text-sm">Konfigurasi Scanner</span>
            </div>
            <div class="p-6 space-y-5">

                <!-- Pilih Sesi -->
                <div>
                    <label for="sesi_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-list-check mr-1 text-blue-500"></i>Pilih Sesi Presensi
                    </label>
                    <select x-model="sesiTerpilih" id="sesi_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Sesi --</option>
                        <?php foreach ($sesi_list as $sesi_item): ?>
                            <option value="<?php echo $sesi_item['id']; ?>"><?php echo htmlspecialchars($sesi_item['nama_sesi']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Divider -->
                <div class="border-t border-gray-100"></div>

                <!-- Pilih Kamera -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-camera mr-1 text-blue-500"></i>Pilih Kamera
                    </label>

                    <!-- Loading kamera -->
                    <div x-show="!camerasLoaded"
                         class="flex items-center gap-2 px-3 py-2.5 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-600">
                        <i class="fas fa-spinner fa-spin text-blue-400"></i>
                        <span>Mendeteksi kamera yang tersedia...</span>
                    </div>

                    <!-- Error kamera -->
                    <div x-show="camerasLoaded && camerasError"
                         class="flex items-start gap-2 px-3 py-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        <i class="fas fa-triangle-exclamation mt-0.5 flex-shrink-0"></i>
                        <span x-text="camerasError"></span>
                    </div>

                    <!-- Dropdown kamera -->
                    <div x-show="camerasLoaded && !camerasError">
                        <div class="flex gap-2">
                            <select x-model="selectedCamera"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <template x-for="(cam, idx) in cameras" :key="cam.id">
                                    <option :value="cam.id"
                                            x-text="cam.label ? cam.label : ('Kamera ' + (idx + 1))"></option>
                                </template>
                            </select>
                            <button @click="isMirrored = !isMirrored" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 focus:outline-none flex items-center justify-center transition-colors shadow-sm"
                                    :class="{ 'bg-blue-50 text-blue-600 border-blue-300': isMirrored }"
                                    title="Mirror Kamera">
                                <i class="fas fa-right-left"></i>
                            </button>
                        </div>
                        <p x-show="cameras.length > 1" class="mt-1.5 text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-0.5"></i>
                            <span x-text="cameras.length + ' kamera terdeteksi. Pilih kamera yang ingin digunakan.'"></span>
                        </p>
                        <p x-show="cameras.length === 1" class="mt-1.5 text-xs text-gray-400">
                            <i class="fas fa-check-circle text-green-400 mr-0.5"></i>
                            1 kamera terdeteksi.
                        </p>
                    </div>

                    <!-- Tombol refresh kamera -->
                    <button @click="loadCameras()"
                            class="mt-2 text-xs text-blue-500 hover:text-blue-700 flex items-center gap-1 transition-colors">
                        <i class="fas fa-arrows-rotate"></i> Muat ulang daftar kamera
                    </button>
                </div>

                <!-- Tombol Mulai -->
                <button @click="mulaiSesi"
                        :disabled="!sesiTerpilih || !selectedCamera || !camerasLoaded"
                        class="w-full px-4 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-qrcode"></i> Mulai Presensi
                </button>

            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- Tampilan Scanner Aktif                                  -->
    <!-- ===================================================== -->
    <div x-show="sesiAktif" x-transition x-cloak class="mt-6 w-full flex flex-col items-center">

        <!-- Info Bar: Sesi + Ganti Sesi -->
        <div class="hide-in-fs w-full max-w-lg flex items-center justify-between bg-blue-50 border border-blue-200 rounded-xl px-5 py-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse inline-block"></span>
                <span class="text-sm text-gray-700">Sesi Aktif:</span>
                <span class="font-bold text-blue-700" x-text="namaSesiTerpilih"></span>
            </div>
            <div class="flex items-center gap-2">
                <button @click="toggleFullscreen"
                        class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-1.5 shadow-sm">
                    <i class="fas fa-expand"></i> Fullscreen
                </button>
                <button @click="gantiSesi"
                        class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                    <i class="fas fa-arrow-left"></i> Ganti
                </button>
            </div>
        </div>

        <!-- Pemilih Kamera & Toggle Mirror (tampil hanya jika ada lebih dari 1 kamera) -->
        <div class="hide-in-fs mt-3 flex w-full max-w-lg items-center gap-2">
            <div x-show="cameras.length > 1"
                 class="flex-1 flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-4 py-2.5 shadow-sm">
                <i class="fas fa-camera text-blue-400 text-sm flex-shrink-0"></i>
                <span class="text-xs text-gray-500 flex-shrink-0 font-medium">Kamera:</span>
                <div class="flex-1 relative">
                    <select x-model="selectedCamera"
                            class="w-full text-xs font-semibold text-gray-700 bg-transparent border-0 focus:ring-0 cursor-pointer pr-5 appearance-none">
                        <template x-for="(cam, idx) in cameras" :key="cam.id">
                            <option :value="cam.id"
                                    x-text="cam.label ? cam.label : ('Kamera ' + (idx + 1))"></option>
                        </template>
                    </select>
                    <i class="fas fa-chevron-down text-gray-400 text-xs absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>

            <button @click="isMirrored = !isMirrored" 
                    title="Toggle Mirror Kamera"
                    class="h-[42px] px-4 bg-white border border-gray-200 rounded-xl shadow-sm text-gray-600 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                    :class="{ 'text-blue-600 bg-blue-50 border-blue-200': isMirrored, 'w-full': cameras.length <= 1 }">
                <i class="fas fa-right-left"></i> <span class="text-xs font-semibold">Mirror Kamera</span>
            </button>
        </div>

        <!-- Scanner Area -->
        <div class="scanner-container max-w-lg mx-auto mt-3 w-full">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="hide-in-fs bg-blue-600 px-6 py-3 flex items-center gap-2">
                    <i class="fas fa-camera text-white text-sm"></i>
                    <span class="text-white font-semibold text-sm">Kamera Scanner</span>
                </div>
                <div class="p-4">
                    <div class="w-full border-2 border-blue-200 rounded-lg overflow-hidden bg-gray-100">
                        <div id="reader" class="w-full" :class="{ 'mirror-video': isMirrored }"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ======================================================= -->
    <!-- Overlay Notifikasi Hasil Scan (Terpusat & Animasi)       -->
    <!-- ======================================================= -->
    <div x-show="scanResult.visible"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-6"
         style="display:none;">

        <template x-if="scanResult.visible">
            <div class="scan-result-card bg-white rounded-3xl shadow-2xl p-8 max-w-xs w-full text-center">

                <!-- Memproses -->
                <template x-if="scanResult.status === 'info'">
                    <div class="mb-4 flex flex-col items-center">
                        <div class="relative w-20 h-20 flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full bg-blue-100 pulse-ring"></div>
                            <div class="w-14 h-14 rounded-full border-4 border-blue-200 border-t-blue-500 animate-spin"></div>
                        </div>
                    </div>
                </template>

                <!-- Berhasil — animasi centang -->
                <template x-if="scanResult.status === 'success'">
                    <div class="mb-4 flex justify-center">
                        <svg class="w-20 h-20" viewBox="0 0 52 52" fill="none">
                            <circle class="anim-circle" cx="26" cy="26" r="25" stroke="#22c55e" stroke-width="2"/>
                            <path  class="anim-check" d="M14 27l8 8 16-16" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </template>

                <!-- Gagal — animasi silang -->
                <template x-if="scanResult.status === 'error'">
                    <div class="mb-4 flex justify-center">
                        <svg class="w-20 h-20" viewBox="0 0 52 52" fill="none">
                            <circle class="anim-circle" cx="26" cy="26" r="25" stroke="#ef4444" stroke-width="2"/>
                            <line  class="anim-cross1" x1="17" y1="17" x2="35" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                            <line  class="anim-cross2" x1="35" y1="17" x2="17" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                </template>

                <p class="font-semibold text-gray-800 text-base leading-snug" x-text="scanResult.message"></p>
                <p class="text-xs text-gray-400 mt-2" x-show="scanResult.status !== 'info'">Melanjutkan otomatis...</p>
            </div>
        </template>
    </div>

    <!-- Exit Fullscreen Button (Scroll ke Bawah) -->
    <div class="fs-only-flex w-full justify-center mt-[50vh] pb-12">
        <button @click="toggleFullscreen" class="px-6 py-3 bg-red-900/40 hover:bg-red-600 text-red-200 hover:text-white border border-red-800/50 hover:border-red-500 rounded-xl transition-all duration-300 flex items-center gap-2 cursor-pointer shadow-lg">
            <i class="fas fa-compress"></i> Keluar dari Fullscreen
        </button>
    </div>

</div>