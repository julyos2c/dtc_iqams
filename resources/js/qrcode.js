import QRCodeRenderer from 'qrcode';

class QRCode {
    constructor(target, options = {}) {
        this.target = typeof target === 'string' ? document.getElementById(target) : target;
        this.canvas = document.createElement('canvas');
        this.target?.appendChild(this.canvas);

        QRCodeRenderer.toCanvas(this.canvas, options.text || '', {
            width: options.width || 200,
            margin: 1,
            color: {
                dark: options.colorDark || '#000000',
                light: options.colorLight || '#ffffff',
            },
        }).catch(() => {
            if (this.target) this.target.textContent = 'QR code could not be generated.';
        });
    }

    clear() {
        this.target?.replaceChildren();
    }
}

window.QRCode = QRCode;

const loadImage = (source) => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = source;
});

const fitText = (context, text, maxWidth, startingSize, weight = 700, minimumSize = 14) => {
    let size = startingSize;
    do {
        context.font = `${weight} ${size}px Arial, sans-serif`;
        size -= 2;
    } while (size > minimumSize && context.measureText(text).width > maxWidth);
};

const drawRoundedRect = (context, x, y, width, height, radius) => {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fill();
};

window.downloadIqamsIdCard = async (endpoint) => {
    const response = await fetch(endpoint, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'The ID card could not be downloaded.');
    }

    const card = document.createElement('canvas');
    card.width = 1012;
    card.height = 638;
    const context = card.getContext('2d');

    const gradient = context.createLinearGradient(0, 0, card.width, card.height);
    gradient.addColorStop(0, '#10294b');
    gradient.addColorStop(1, '#2563eb');
    context.fillStyle = gradient;
    drawRoundedRect(context, 0, 0, card.width, card.height, 38);

    context.fillStyle = '#ffffff';
    drawRoundedRect(context, 24, 24, 964, 590, 28);
    context.fillStyle = '#f0fdfa';
    drawRoundedRect(context, 48, 116, 596, 470, 22);

    const [logo, avatar, fallbackAvatar] = await Promise.all([
        loadImage(data.logo_url).catch(() => null),
        loadImage(data.avatar_url).catch(() => null),
        loadImage(data.fallback_avatar_url).catch(() => null),
    ]);

    if (logo) context.drawImage(logo, 50, 42, 58, 58);
    context.fillStyle = '#10294b';
    context.font = '800 34px Arial, sans-serif';
    context.fillText('IQAMS', 124, 76);
    context.fillStyle = '#64748b';
    context.font = '500 16px Arial, sans-serif';
    context.fillText('QR ATTENDANCE IDENTIFICATION', 124, 98);

    context.save();
    context.beginPath();
    context.arc(176, 252, 94, 0, Math.PI * 2);
    context.clip();
    const profileImage = avatar || fallbackAvatar;
    if (profileImage) {
        const side = Math.min(profileImage.naturalWidth, profileImage.naturalHeight);
        const sourceX = (profileImage.naturalWidth - side) / 2;
        const sourceY = (profileImage.naturalHeight - side) / 2;
        context.drawImage(profileImage, sourceX, sourceY, side, side, 82, 158, 188, 188);
    } else {
        context.fillStyle = '#cbd5e1';
        context.fillRect(82, 158, 188, 188);
    }
    context.restore();
    context.strokeStyle = '#ffffff';
    context.lineWidth = 8;
    context.beginPath();
    context.arc(176, 252, 98, 0, Math.PI * 2);
    context.stroke();

    context.fillStyle = '#10294b';
    fitText(context, data.name, 322, 34, 800, 20);
    context.fillText(data.name, 310, 188);
    context.fillStyle = '#2563eb';
    context.font = '700 20px Arial, sans-serif';
    context.fillText(data.role, 310, 220);

    const details = [
        [data.identifier_label, data.identifier],
        ...(data.office ? [['Office / Unit', data.office]] : (data.department ? [['Department', data.department]] : [])),
        ...(data.course ? [['Course', data.course]] : []),
        ...(data.section ? [['Section', data.section]] : []),
        ...(data.year_level ? [['Year Level', data.year_level]] : []),
    ];
    details.forEach(([label, value], index) => {
        const y = 265 + index * 64;
        context.fillStyle = '#64748b';
        context.font = '600 15px Arial, sans-serif';
        context.fillText(label.toUpperCase(), 310, y);
        context.fillStyle = '#0f172a';
        fitText(context, String(value), 300, 23, 700);
        context.fillText(String(value), 310, y + 29);
    });

    context.fillStyle = '#ffffff';
    drawRoundedRect(context, 676, 116, 280, 390, 22);
    context.strokeStyle = '#dbeafe';
    context.lineWidth = 3;
    context.strokeRect(677.5, 117.5, 277, 387);

    const qrCanvas = document.createElement('canvas');
    await QRCodeRenderer.toCanvas(qrCanvas, data.qr_code, {
        width: 252,
        margin: 4,
        errorCorrectionLevel: 'H',
        color: { dark: '#0f172a', light: '#ffffff' },
    });
    context.imageSmoothingEnabled = false;
    context.drawImage(qrCanvas, 690, 132, 252, 252);
    context.imageSmoothingEnabled = true;
    context.fillStyle = '#10294b';
    context.font = '700 17px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('SCAN FOR ATTENDANCE', 816, 422);
    context.fillStyle = '#64748b';
    context.font = '500 14px Arial, sans-serif';
    context.fillText(data.identifier, 816, 451);
    context.textAlign = 'left';

    context.fillStyle = '#10294b';
    drawRoundedRect(context, 676, 526, 280, 60, 16);
    context.fillStyle = '#ffffff';
    context.font = '600 14px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('Official IQAMS Identification Card', 816, 563);

    const blob = await new Promise((resolve, reject) => card.toBlob(
        (result) => result ? resolve(result) : reject(new Error('The ID card image could not be created.')),
        'image/png',
    ));
    const downloadUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = downloadUrl;
    anchor.download = data.filename;
    anchor.click();
    URL.revokeObjectURL(downloadUrl);
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { title: 'Success', message: 'ID card downloaded successfully.' },
    }));
};

const escapePrintText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));

const fetchIdCard = async (endpoint) => {
    const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'The ID card could not be loaded.');
    return data;
};

const qrDataUrl = async (value) => {
    const canvas = document.createElement('canvas');
    await QRCodeRenderer.toCanvas(canvas, value, { width: 260, margin: 4, errorCorrectionLevel: 'H', color: { dark: '#0f172a', light: '#ffffff' } });
    return canvas.toDataURL('image/png');
};

const printStyles = `<style>
@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box}html,body{margin:0;padding:0}body{font-family:Arial,sans-serif;color:#10294b;background:#fff}.card{position:relative;width:54mm;height:85.6mm;border:1px solid #dce4ef;border-radius:4mm;overflow:hidden;text-align:center;break-inside:avoid;page-break-after:always;display:flex;flex-direction:column;align-items:center;padding:5mm 3mm 3mm}.card:before{content:"";position:absolute;top:0;left:0;right:0;height:1.4mm;background:#10294b}.brand{display:flex;align-items:center;justify-content:center;gap:1.5mm;font-weight:800;font-size:3.4mm;letter-spacing:.7mm}.brand-mark{display:grid;place-items:center;width:6mm;height:6mm;border-radius:1.7mm;background:#10294b;color:white;font-size:2.8mm;letter-spacing:0}.role{margin:1.4mm 0 0;color:#60789a;font-weight:700;font-size:2.2mm;letter-spacing:.45mm;text-transform:uppercase}.avatar{width:16mm;height:16mm;flex:none;object-fit:cover;border-radius:50%;border:1mm solid white;outline:1px solid #dce4ef;background:#dbeafe;margin:3mm 0 2mm}.identity{width:100%;min-width:0;height:17mm;flex:none;overflow:hidden}.identity h1{font-size:3.2mm;line-height:1.15;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.identity p{font-size:2.35mm;line-height:1.25;margin:1mm 0 0;color:#526a89;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.identity .identifier{font-weight:700;color:#405979}.details{font-size:2mm!important}.qr{flex:none;margin-top:auto;border:1px solid #dce4ef;border-radius:2mm;padding:1mm;background:#fff}.qr img{display:block;width:25mm;height:25mm}.scan{font-size:2mm;font-weight:800;letter-spacing:.35mm;margin:1.5mm 0 0;white-space:nowrap;flex:none}@media screen{body{padding:12px;background:#f2f5fa}.card{margin:0 auto 12px;background:white;box-shadow:0 8px 24px rgba(15,23,42,.12)}}@media print{.card{margin:0;box-shadow:none;print-color-adjust:exact;-webkit-print-color-adjust:exact}}
</style>`;

const printCard = (data, qrImage) => {
    const role = data.role === 'Teaching Personnel' ? 'Instructor' : (data.role === 'Non-Teaching Personnel' ? 'Staff' : data.role);
    const assignment = data.office || data.department;
    const extraDetails = [data.course, data.section, data.year_level].filter(Boolean).map(escapePrintText).join(' / ');
    return `<article class="card"><div class="brand"><span class="brand-mark">I</span><span>IQAMS</span></div><p class="role">${escapePrintText(role)} ID Card</p><img class="avatar" src="${escapePrintText(data.avatar_url)}" onerror="this.onerror=null;this.src='${escapePrintText(data.fallback_avatar_url || '')}'" alt=""><div class="identity"><h1>${escapePrintText(data.name)}</h1><p class="identifier">${escapePrintText(data.identifier_label)}: ${escapePrintText(data.identifier)}</p>${assignment ? `<p>${escapePrintText(assignment)}</p>` : ''}${extraDetails ? `<p class="details">${extraDetails}</p>` : ''}</div><div class="qr"><img src="${qrImage}" alt="QR code"></div><p class="scan">SCAN FOR ATTENDANCE</p></article>`;
};

const openPrintWindow = (cards) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    printWindow.document.write(`<html><head><meta charset="utf-8"><title>IQAMS ID Cards</title>${printStyles}</head><body>${cards.join('')}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.onload = () => { printWindow.print(); printWindow.close(); };
};

window.printIqamsIdCard = async (endpoint) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    try {
        const data = await fetchIdCard(endpoint);
        const qrImage = await qrDataUrl(data.qr_code);
        printWindow.document.write(`<html><head><meta charset="utf-8"><title>IQAMS ID Card</title></head><body></body></html>`);
        printWindow.document.close();
        printWindow.document.body.innerHTML = `${printStyles}${printCard(data, qrImage)}`;
        printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    } catch (error) {
        printWindow.close();
        throw error;
    }
};

window.printIqamsIdCards = async (endpoints) => {
    if (!endpoints.length) throw new Error('Select at least one user.');
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    try {
        const data = await Promise.all(endpoints.map(fetchIdCard));
        const cards = await Promise.all(data.map(async (card) => printCard(card, await qrDataUrl(card.qr_code))));
        printWindow.document.write(`<html><head><meta charset="utf-8"><title>IQAMS ID Cards</title>${printStyles}</head><body>${cards.join('')}</body></html>`);
        printWindow.document.close();
        printWindow.focus();
        printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    } catch (error) {
        printWindow.close();
        throw error;
    }
};

window.dispatchEvent(new CustomEvent('qrcode:ready'));
