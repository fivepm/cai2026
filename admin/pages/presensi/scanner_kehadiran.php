<?php
// (File: admin/pages/presensi/scanner_kehadiran.php)

$sesi_list = [];
$result_sesi_list = $conn->query("SELECT id, nama_sesi FROM sesi_presensi ORDER BY nama_sesi");
if ($result_sesi_list) {
    while ($row = $result_sesi_list->fetch_assoc()) {
        $sesi_list[] = $row;
    }
}
?>

<script>
    function scannerKehadiranData() {
        return {
            sesiTerpilih: '',
            sesiAktif: false,
            namaSesiTerpilih: '',
            scanResult: { status: '', message: '' },
            html5QrCode: null,

            mulaiSesi() {
                if (!this.sesiTerpilih) return;
                const selectedOption = document.querySelector(`#sesi_id option[value='${this.sesiTerpilih}']`);
                this.namaSesiTerpilih = selectedOption.textContent;
                this.sesiAktif = true;
                this.scanResult = { status: '', message: '' };
                this.$nextTick(() => { this.startScanner(); });
            },

            gantiSesi() {
                this.stopScanner();
                this.sesiAktif = false;
                this.sesiTerpilih = '';
                this.scanResult = { status: '', message: '' };
            },

            startScanner() {
                if (!this.html5QrCode) this.html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                this.html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    (decodedText) => { this.html5QrCode.pause(); this.handleScan(decodedText); },
                    () => {}
                ).catch(() => {
                    this.scanResult = { status: 'error', message: 'Gagal memulai kamera. Pastikan Anda memberikan izin dan menggunakan HTTPS.' };
                });
            },

            stopScanner() {
                if (this.html5QrCode && this.html5QrCode.isScanning) {
                    this.html5QrCode.stop().catch(err => console.error("Gagal menghentikan scanner.", err));
                }
            },

            handleScan(barcode) {
                this.scanResult = { status: 'info', message: 'Memproses...' };
                fetch('pages/presensi/api_presensi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ barcode: barcode, sesi_id: this.sesiTerpilih })
                })
                .then(response => {
                    if (!response.ok) return response.json().then(err => { throw new Error(err.message || 'Error tidak diketahui'); });
                    return response.json();
                })
                .then(data => { this.scanResult = data; })
                .catch(error => {
                    this.scanResult = { status: 'error', message: `Terjadi masalah. Error: ${error.message}` };
                })
                .finally(() => {
                    setTimeout(() => {
                        if (this.html5QrCode && this.sesiAktif) this.html5QrCode.resume();
                    }, 2000);
                });
            }
        };
    }
</script>

<div x-data="scannerKehadiranData()">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Scanner Kehadiran</h1>
            <p class="text-sm text-gray-500 mt-0.5">Scan QR Code peserta untuk mencatat kehadiran</p>
        </div>
        <!-- Widget Jam Real-Time -->
        <div class="flex-shrink-0 bg-gradient-to-br from-blue-700 to-blue-900 rounded-2xl shadow-lg p-4 text-white flex items-center gap-4 min-w-[220px]">
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
            const h=Math.floor(Math.abs(offset)/60), m=Math.abs(offset)%60, sign=offset>=0?'+':'-';
            return 'UTC'+sign+String(h).padStart(2,'0')+(m?':'+String(m).padStart(2,'0'):'');
        }
        function drawAnalogClock(canvas, h, m, s) {
            const ctx=canvas.getContext('2d'), cx=canvas.width/2, cy=canvas.height/2, r=cx-3;
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.beginPath(); ctx.arc(cx,cy,r,0,2*Math.PI); ctx.fillStyle='rgba(255,255,255,0.12)'; ctx.fill();
            ctx.strokeStyle='rgba(255,255,255,0.4)'; ctx.lineWidth=1.5; ctx.stroke();
            for(let i=0;i<12;i++){
                const a=(i/12)*2*Math.PI-Math.PI/2;
                ctx.beginPath(); ctx.arc(cx+Math.cos(a)*(r-5),cy+Math.sin(a)*(r-5),i%3===0?2:1,0,2*Math.PI);
                ctx.fillStyle='rgba(255,255,255,0.7)'; ctx.fill();
            }
            function drawHand(angle,length,width,color){
                ctx.save(); ctx.translate(cx,cy); ctx.rotate(angle);
                ctx.beginPath(); ctx.moveTo(0,length*0.2); ctx.lineTo(0,-length);
                ctx.strokeStyle=color; ctx.lineWidth=width; ctx.lineCap='round'; ctx.stroke(); ctx.restore();
            }
            drawHand(((h%12)/12+m/720+s/43200)*2*Math.PI, r*0.5, 3, 'rgba(255,255,255,0.95)');
            drawHand((m/60+s/3600)*2*Math.PI, r*0.72, 2, 'rgba(255,255,255,0.9)');
            drawHand((s/60)*2*Math.PI, r*0.78, 1, '#fbbf24');
            ctx.beginPath(); ctx.arc(cx,cy,3,0,2*Math.PI); ctx.fillStyle='#fbbf24'; ctx.fill();
        }
        function updateClock() {
            const now=new Date(), h=now.getHours(), m=now.getMinutes(), s=now.getSeconds();
            document.getElementById('clock-time').textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            document.getElementById('clock-date').textContent=HARI[now.getDay()]+', '+now.getDate()+' '+BULAN[now.getMonth()]+' '+now.getFullYear();
            document.getElementById('clock-timezone').textContent=getTimezoneLabel(-now.getTimezoneOffset());
            const canvas=document.getElementById('analog-clock');
            if(canvas) drawAnalogClock(canvas,h,m,s);
        }
        updateClock(); setInterval(updateClock,1000);
    })();
    </script>

    <!-- Tampilan Awal: Pilih Sesi -->
    <div x-show="!sesiAktif" x-transition class="mt-6">
        <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
                <i class="fas fa-calendar-check text-white text-sm"></i>
                <span class="text-white font-semibold text-sm">Pilih Sesi Presensi</span>
            </div>
            <div class="p-6">
                <label for="sesi_id" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-list-check mr-1 text-blue-500"></i>Sesi yang Tersedia
                </label>
                <select x-model="sesiTerpilih" id="sesi_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Pilih Sesi --</option>
                    <?php foreach ($sesi_list as $sesi_item): ?>
                        <option value="<?php echo $sesi_item['id']; ?>"><?php echo htmlspecialchars($sesi_item['nama_sesi']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button @click="mulaiSesi" :disabled="!sesiTerpilih"
                        class="mt-4 w-full px-4 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-qrcode"></i> Mulai Presensi
                </button>
            </div>
        </div>
    </div>

    <!-- Tampilan Scanner -->
    <div x-show="sesiAktif" x-transition x-cloak class="mt-6">
        <!-- Info Bar -->
        <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-xl px-5 py-3 mb-5">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse inline-block"></span>
                <span class="text-sm text-gray-700">Sesi Aktif:</span>
                <span class="font-bold text-blue-700" x-text="namaSesiTerpilih"></span>
            </div>
            <button @click="gantiSesi"
                    class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1.5">
                <i class="fas fa-arrow-left"></i> Ganti Sesi
            </button>
        </div>

        <!-- Scanner Area -->
        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
                    <i class="fas fa-camera text-white text-sm"></i>
                    <span class="text-white font-semibold text-sm">Kamera Scanner</span>
                </div>
                <div class="p-4">
                    <div id="scanner-container" class="w-full border-2 border-blue-200 rounded-lg overflow-hidden bg-gray-100">
                        <div id="reader" class="w-full"></div>
                    </div>
                </div>
            </div>

            <!-- Area Hasil Scan -->
            <div id="result-container" class="mt-4">
                <div x-show="scanResult.message"
                     :class="{
                         'bg-green-50 text-green-800 border-green-300': scanResult.status === 'success',
                         'bg-red-50 text-red-800 border-red-300': scanResult.status === 'error',
                         'bg-blue-50 text-blue-800 border-blue-300': scanResult.status === 'info'
                     }"
                     class="p-4 border rounded-xl flex items-center gap-3 shadow-sm">
                    <i :class="{
                           'fas fa-circle-check text-green-500 text-xl': scanResult.status === 'success',
                           'fas fa-circle-xmark text-red-500 text-xl': scanResult.status === 'error',
                           'fas fa-spinner fa-spin text-blue-500 text-xl': scanResult.status === 'info'
                       }"></i>
                    <span class="font-medium text-sm" x-text="scanResult.message"></span>
                </div>
            </div>
        </div>
    </div>

</div>