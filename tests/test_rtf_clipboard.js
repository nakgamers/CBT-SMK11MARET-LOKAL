const fs = require('fs');

// Ambil fungsi produksi langsung dari view agar tes tidak menduplikasi logika.
const src = fs.readFileSync('app/Views/admin/soal.php', 'utf8');
const match = src.match(/function gambarDariRtf\(rtf\) \{([\s\S]*?)\n\}/);
if (!match) {
  console.error('GAGAL: fungsi gambarDariRtf belum ada');
  process.exit(1);
}
const gambarDariRtf = new Function('rtf', match[1]);

function yes(kondisi, pesan) {
  if (!kondisi) { console.error('GAGAL:', pesan); process.exit(1); }
  console.log('OK', pesan);
}

const rtf = String.raw`{\rtf1\ansi
teks sebelum
{\pict\pngblip\picw1\pich1
89504e470d0a1a0a0000000d49484452
}
teks tengah
{\pict\jpegblip\picw2\pich2
ffd8ffe000104a4649460001ffd9
}
}`;
const hasil = gambarDariRtf(rtf);
yes(hasil.length === 2, 'dua gambar diekstrak dari clipboard RTF');
yes(hasil[0].type === 'image/png', 'format PNG dikenali');
yes(hasil[1].type === 'image/jpeg', 'format JPEG dikenali');
yes(hasil[0].bytes[0] === 0x89 && hasil[0].bytes[1] === 0x50, 'hex PNG menjadi byte');
yes(hasil[1].bytes[0] === 0xff && hasil[1].bytes.at(-1) === 0xd9, 'hex JPEG menjadi byte');

yes(gambarDariRtf('{\\rtf1 tanpa gambar}').length === 0, 'RTF tanpa gambar aman');
yes(gambarDariRtf('{\\pict\\emfblip 01000000}').length === 0, 'EMF tidak dipaksakan sebagai gambar browser');
console.log('SEMUA TES RTF LULUS');
