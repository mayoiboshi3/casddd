<?php
/**
 * pdf_generator.php
 * ------------------------------------------------------------------
 * Client-side PDF export logic for the Verified / Resolved reports
 * table (reports.php). Split out of reports.php to keep that file
 * shorter and to isolate everything PDF-related in one place.
 *
 * This file is pure browser JavaScript (jsPDF), included directly
 * inside reports.php's existing <script> ... </script> block via:
 *     <?php include __DIR__ . '/pdf_generator.php'; ?>
 * It relies on globals defined earlier in reports.php's script block
 * (e.g. currentTabReports) and is in turn called by the "Export"
 * buttons' onclick handlers (exportSingleReport / exportAllReports)
 * that live in reports.php's markup.
 *
 * Requires jsPDF to already be loaded on the page (see the
 * <script src=".../jspdf.umd.min.js"> tag in reports.php).
 *
 * SECTION RULES (per case, based on status):
 *   VERIFIED  -> General Information, Identified Disease,
 *                Photo Evidence (with timestamp caption), Field Observations.
 *                No recommendation content — verified cases haven't
 *                necessarily been closed out with the farmer yet.
 *   RESOLVED  -> Same four sections, PLUS "Recommendation Provided" — a
 *                formal, summarized record of the treatment advice CASD
 *                staff sent to the farmer for this case. This is a case
 *                record entry, not a chat transcript: only the staff's
 *                own recommendation text is included; farmer replies are
 *                intentionally omitted so the report reads as an official
 *                document rather than a message log.
 *   Both      -> Reviewer Remarks, only if present.
 * ------------------------------------------------------------------
 */
?>
/* ═══════════════════════════════════════════════════
   EXPORT REPORTS TO PDF (real .pdf file, direct download)
   Uses jsPDF (loaded above). doc.save() writes an actual
   PDF file to the browser's downloads folder — no print
   dialog required.

   Formal letterhead layout: CASD logo (upper-left) + org
   title, a repeated header/footer on every page, and a
   serif typeface for a more official document feel.
═══════════════════════════════════════════════════ */
function escPdf(str) { return str == null ? '' : String(str); }

// Path to the agency logo file used in the letterhead. Update this if the
// logo is stored somewhere other than the same folder as reports.php.
const PDF_LOGO_PATH = 'casdlogo.jpg';

const PDF_MARGIN_X     = 15;
const PDF_CONTENT_W    = 180;
const PDF_HEADER_Y     = 42;  // where case content starts on every page, below the letterhead
const PDF_FOOTER_SAFE  = 22;  // bottom margin reserved for the footer

// Every case's Photo Evidence image is displayed inside this exact frame size (mm),
// so the frame lines up identically from one case to the next. The photo itself is
// scaled to FIT inside the frame (preserving its original aspect ratio) and centered —
// never stretched and never cropped.
const PDF_PHOTO_W      = PDF_CONTENT_W;
const PDF_PHOTO_H      = 85;

// Generic image loader: fetches an image URL and converts it to a PNG data URL via canvas,
// so any source format can be embedded in the PDF. Resolves null on failure (never blocks export).
function loadImageAsDataURL(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            try {
                const canvas = document.createElement('canvas');
                canvas.width  = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                resolve({ dataUrl: canvas.toDataURL('image/png'), width: img.naturalWidth, height: img.naturalHeight });
            } catch (e) {
                resolve(null); // e.g. canvas tainted by cross-origin restrictions
            }
        };
        img.onerror = function () { resolve(null); };
        img.src = url;
    });
}

function loadPhotoForPdf(filename) {
    if (!filename) return Promise.resolve(null);
    return loadImageAsDataURL('uploads/' + filename);
}

let _cachedLogo; // undefined = not yet attempted; null = attempted and unavailable
async function loadLogoForPdf() {
    if (_cachedLogo === undefined) _cachedLogo = await loadImageAsDataURL(PDF_LOGO_PATH);
    return _cachedLogo;
}

// Formats a MySQL-style datetime string into a compact, human-readable timestamp.
// Falls back to returning the raw value if it can't be parsed.
function formatPdfTimestamp(raw) {
    if (!raw) return '';
    const d = new Date(String(raw).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(raw);
    return d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: 'numeric', minute: '2-digit' });
}

// Draws the letterhead (logo + agency name + report title + rule) on the CURRENT page.
function drawLetterhead(doc, logo, reportTitle) {
    const pageWidth = doc.internal.pageSize.getWidth();
    let textX = PDF_MARGIN_X;

    if (logo) {
        const logoH = 16;
        const logoW = (logo.width && logo.height) ? (logo.width / logo.height) * logoH : logoH;
        doc.addImage(logo.dataUrl, 'PNG', PDF_MARGIN_X, 9, logoW, logoH);
        textX = PDF_MARGIN_X + logoW + 5;
    }

    doc.setFont('times', 'bold'); doc.setFontSize(13); doc.setTextColor(20);
    doc.text('City Agricultural Services Department ', textX, 15);
    doc.setFont('times', 'normal'); doc.setFontSize(8.5); doc.setTextColor(100);
    doc.text('City Government of Calamba - Disease Surveillance ', textX, 20);

    doc.setFont('times', 'italic'); doc.setFontSize(9); doc.setTextColor(60);
    doc.text(reportTitle, pageWidth - PDF_MARGIN_X, 15, { align: 'right' });
    doc.setFont('times', 'normal'); doc.setFontSize(7.5); doc.setTextColor(130);
    doc.text('Generated ' + new Date().toLocaleString('en-PH'), pageWidth - PDF_MARGIN_X, 20, { align: 'right' });

    doc.setDrawColor(20); doc.setLineWidth(0.6);
    doc.line(PDF_MARGIN_X, 29, pageWidth - PDF_MARGIN_X, 29);
    doc.setLineWidth(0.2);
    doc.line(PDF_MARGIN_X, 30.3, pageWidth - PDF_MARGIN_X, 30.3);
    doc.setTextColor(30);
}

// Draws the footer (rule + confidentiality note + page number) on the CURRENT page.
function drawFooter(doc, pageNum, pageCount) {
    const pageWidth  = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const y = pageHeight - 12;
    doc.setDrawColor(200); doc.setLineWidth(0.2);
    doc.line(PDF_MARGIN_X, y - 4, pageWidth - PDF_MARGIN_X, y - 4);
    doc.setFont('times', 'normal'); doc.setFontSize(7.5); doc.setTextColor(130);
    doc.text('Official Use Only \u2014 Disease Case Report', PDF_MARGIN_X, y);
    doc.text(`Page ${pageNum} of ${pageCount}`, pageWidth - PDF_MARGIN_X, y, { align: 'right' });
    doc.setTextColor(30);
}

// Stamps the letterhead + footer onto every page of the finished document.
function finalizeLetterheadedDoc(doc, reportTitle) {
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        drawLetterhead(doc, _cachedLogo, reportTitle);
        drawFooter(doc, i, pageCount);
    }
}

// Numbered section header — formal document style: bold small-caps-style label
// with a plain rule underneath (no fill/color band). Section numbers are
// assigned dynamically per-case since VERIFIED and RESOLVED cases show
// different sets of sections (see file header note above).
function addSectionHeader(doc, num, title, x, y, maxWidth, pageHeight) {
    if (y > pageHeight - PDF_FOOTER_SAFE - 10) { doc.addPage(); y = PDF_HEADER_Y; }
    doc.setFont('times', 'bold'); doc.setFontSize(10.5); doc.setTextColor(20);
    doc.text(`${num}. ${title.toUpperCase()}`, x, y);
    doc.setDrawColor(20); doc.setLineWidth(0.4);
    doc.line(x, y + 2.2, x + maxWidth, y + 2.2);
    doc.setTextColor(30);
    return y + 9;
}

// Plain wrapped paragraph, no label — used under section headers for free text.
// Supports blank-line-separated paragraphs (a double "\n\n" starts a new block
// with a little extra breathing room), which the Recommendation Provided section
// relies on when there are multiple staff messages to summarize.
function addWrappedText(doc, text, x, y, maxWidth, pageHeight, opts) {
    opts = opts || {};
    doc.setFont('times', opts.italic ? 'italic' : 'normal');
    doc.setFontSize(opts.size || 9.5);
    doc.setTextColor(opts.gray != null ? opts.gray : 30);

    const paragraphs = String(text).split(/\n\s*\n/);
    paragraphs.forEach((para, pIdx) => {
        doc.splitTextToSize(para.trim(), maxWidth).forEach(line => {
            if (y > pageHeight - PDF_FOOTER_SAFE) { doc.addPage(); y = PDF_HEADER_Y; }
            doc.text(line, x, y);
            y += 5;
        });
        if (pIdx < paragraphs.length - 1) y += 2; // small gap between paragraphs
    });

    doc.setTextColor(30);
    return y + 4;
}

// Labeled key:value line, used inside the General Information section.
function addInfoRow(doc, label, val, x, y, pageHeight) {
    if (y > pageHeight - PDF_FOOTER_SAFE - 6) { doc.addPage(); y = PDF_HEADER_Y; }
    doc.setFont('times', 'bold');   doc.setFontSize(9.5); doc.text(label + ':', x, y);
    doc.setFont('times', 'normal'); doc.text(String(val), x + 52, y);
    return y + 6;
}

// Small bold sub-label followed by a wrapped paragraph — used under Identified
// Disease for the disease's official reference info (Recommended Treatment,
// Prevention Measures). Skipped entirely by the caller when there's no text.
function addLabeledParagraph(doc, label, text, x, y, maxWidth, pageHeight) {
    if (y > pageHeight - PDF_FOOTER_SAFE - 10) { doc.addPage(); y = PDF_HEADER_Y; }
    doc.setFont('times', 'bold'); doc.setFontSize(9); doc.setTextColor(20);
    doc.text(label, x, y);
    doc.setTextColor(30);
    y += 5;
    return addWrappedText(doc, text, x, y, maxWidth, pageHeight, { size: 9 });
}

// Formal two-line signature block, printed at the close of every case — this is
// what turns the export from a plain data dump into a signable office document.
// Blank lines are intentional: the printed copy gets physically signed and dated.
function addSignatureBlock(doc, x, y, maxWidth, pageHeight) {
    const blockH = 26;
    if (y + blockH > pageHeight - PDF_FOOTER_SAFE) { doc.addPage(); y = PDF_HEADER_Y; }

    const colW  = (maxWidth - 10) / 2;
    const col1X = x;
    const col2X = x + colW + 10;
    const lineY = y + 14;

    doc.setDrawColor(60); doc.setLineWidth(0.25);
    doc.line(col1X, lineY, col1X + colW, lineY);
    doc.line(col2X, lineY, col2X + colW, lineY);

    doc.setFont('times', 'bold'); doc.setFontSize(9); doc.setTextColor(20);
    doc.text('Prepared by', col1X, lineY + 5);
    doc.text('Noted by', col2X, lineY + 5);

    doc.setFont('times', 'normal'); doc.setFontSize(7.5); doc.setTextColor(110);
    doc.text('Reporting / Verifying Staff', col1X, lineY + 9.5);
    doc.text('Date: _______________', col1X, lineY + 14);
    doc.text('City Agriculturist\u2019s Office', col2X, lineY + 9.5);
    doc.text('Date: _______________', col2X, lineY + 14);

    doc.setTextColor(30);
    return lineY + 19;
}

// Strips the auto-filled "findings" boilerplate (the disease name + field
// observation restatement that gets prepended to every recommendation message,
// e.g. "🔍 Natukoy na Sakit: ..." / "Obserbasyon sa Bukid: ...") out of a staff
// message. That info already has its own sections earlier in the report (Identified
// Disease, Field Observations), so repeating it per-message is just noise here —
// and the leading emoji glyph doesn't render in jsPDF's Times font anyway, which is
// what produces the garbled "Ø<ß=" characters. Only the actual advice text survives.
function cleanRecommendationMessage(text) {
    return String(text)
        .split('\n')
        .filter(line => {
            const t = line.trim();
            if (!t) return true; // keep blank lines — they matter for paragraph spacing
            if (/natukoy\s*na\s*sakit/i.test(t)) return false;
            if (/obserbasyon\s*sa\s*bukid/i.test(t)) return false;
            return true;
        })
        .join('\n')
        .replace(/^(?:\s*\n)+/, '') // drop leftover leading blank lines
        .trim();
}

// Builds the "Recommendation Provided" section body for a RESOLVED case: a formal,
// summarized record of what CASD staff advised the farmer to do — NOT a chat
// transcript. Only staff-authored messages are included (farmer replies are
// deliberately excluded), boilerplate findings text is stripped (see
// cleanRecommendationMessage), and duplicate messages that are identical once
// cleaned are collapsed to one so the same advice isn't repeated verbatim.
function addRecommendationText(doc, messages, x, y, maxWidth, pageHeight) {
    const seen = new Set();
    const staffMessages = (messages || [])
        .filter(m => m && m.sender !== 'farmer' && m.message)
        .map(m => cleanRecommendationMessage(m.message))
        .filter(t => t.length > 0)
        .filter(t => {
            if (seen.has(t)) return false;
            seen.add(t);
            return true;
        });

    if (staffMessages.length === 0) {
        return addWrappedText(
            doc,
            'No recommendation has been recorded for this case.',
            x, y, maxWidth, pageHeight,
            { italic: true, size: 8.5, gray: 130 }
        );
    }

    const combined = staffMessages.join('\n\n');
    return addWrappedText(doc, combined, x, y, maxWidth, pageHeight, { size: 9.5 });
}

async function buildCaseFilePDF(doc, r, y) {
    const pageHeight = doc.internal.pageSize.getHeight();
    const x = PDF_MARGIN_X, maxWidth = PDF_CONTENT_W;

    if (y > pageHeight - PDF_FOOTER_SAFE - 55) { doc.addPage(); y = PDF_HEADER_Y; }

    // Case title block — formal centered heading, plain rule lines, no fill color
    doc.setFont('times', 'bold'); doc.setFontSize(13); doc.setTextColor(20);
    doc.text('DISEASE CASE REPORT', x + maxWidth / 2, y, { align: 'center' });
    y += 6;
    doc.setDrawColor(20); doc.setLineWidth(0.5);
    doc.line(x, y, x + maxWidth, y);
    y += 6;
    doc.setFont('times', 'normal'); doc.setFontSize(9.5); doc.setTextColor(30);
    doc.text(`Reference No.: ${escPdf(r.reference_id) || '\u2014'}`, x, y);
    doc.text(`Status: ${(escPdf(r.status) || '\u2014').toUpperCase()}`, x + maxWidth, y, { align: 'right' });
    y += 4;
    doc.setDrawColor(190); doc.setLineWidth(0.2);
    doc.line(x, y, x + maxWidth, y);
    y += 10;
    doc.setTextColor(30);

    const isResolved = (r.status === 'resolved');
    let sec = 0;

    // ── 1. GENERAL INFORMATION ──
    sec++;
    y = addSectionHeader(doc, sec, 'General Information', x, y, maxWidth, pageHeight);
    const loc = (r.latitude != null && r.longitude != null && r.latitude !== '' && r.longitude !== '')
        ? `${parseFloat(r.latitude).toFixed(6)}, ${parseFloat(r.longitude).toFixed(6)}` + (r.gps_accuracy ? ` (\u00B1${parseFloat(r.gps_accuracy).toFixed(1)}m)` : '')
        : '\u2014';
    y = addInfoRow(doc, 'Farmer',          r.farmer_name  || '\u2014', x, y, pageHeight);
    y = addInfoRow(doc, 'Barangay',        r.brgy_name    || '\u2014', x, y, pageHeight);
    y = addInfoRow(doc, 'Report Date',     r.report_date  || '\u2014', x, y, pageHeight);
    y = addInfoRow(doc, 'Growth Stage',    r.growth_stage || '\u2014', x, y, pageHeight);

    // Some diseases (Common Rust, Northern Leaf Blight, Gray Leaf Spot, Healthy
    // Corn) are tracked by an Infection Rate instead of a Severity level — same
    // swap the on-screen review popup does for these cases.
    if (r.infection_percentage != null && r.infection_percentage !== '') {
        y = addInfoRow(doc, 'Infection Rate', `${parseFloat(r.infection_percentage).toFixed(2)}%`, x, y, pageHeight);
    } else {
        y = addInfoRow(doc, 'Severity', (escPdf(r.severity) || '\u2014').toUpperCase(), x, y, pageHeight);
    }

    y = addInfoRow(doc, 'GPS Location',    loc, x, y, pageHeight);

    // Only shown when a field inspection / farm visit is actually on file for this case.
    // follow_up_date has no time component, so it's formatted as a date only — the exact
    // time (if any) lives in the scheduling message, not this column.
    if (r.follow_up_date) {
        const visitDate = new Date(String(r.follow_up_date) + 'T00:00:00');
        const visitLabel = isNaN(visitDate.getTime())
            ? String(r.follow_up_date)
            : visitDate.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
        y = addInfoRow(doc, 'Next Farm Visit', visitLabel, x, y, pageHeight);
    }
    y += 6;

    // ── 2. IDENTIFIED DISEASE ──
    sec++;
    y = addSectionHeader(doc, sec, 'Identified Disease', x, y, maxWidth, pageHeight);
    doc.setFont('times', 'bold'); doc.setFontSize(12.5); doc.setTextColor(20);
    doc.text(escPdf(r.disease_name) || '\u2014', x, y);
    doc.setTextColor(30);
    y += 7;
    y = addWrappedText(doc, r.disease_description || 'No description on file for this disease.', x, y, maxWidth, pageHeight, { italic: !r.disease_description, gray: r.disease_description ? 30 : 130 });

    // Official reference info for the identified disease — shown for every case
    // (regardless of status) since it's standing reference data, not case-specific
    // advice. Only printed when actually on file for the disease.
    if (r.recommended_treatment) {
        y = addLabeledParagraph(doc, 'Recommended Treatment:', r.recommended_treatment, x, y, maxWidth, pageHeight);
    }
    if (r.prevention_measures) {
        y = addLabeledParagraph(doc, 'Prevention Measures:', r.prevention_measures, x, y, maxWidth, pageHeight);
    }
    y += 4;

    // ── 3. PHOTO EVIDENCE ── (photo_evidence may hold several comma-separated filenames;
    //     first one is embedded, with a small timestamp caption underneath)
    sec++;
    y = addSectionHeader(doc, sec, 'Photo Evidence', x, y, maxWidth, pageHeight);
    const photoFiles = (r.photo_evidence || '').split(',').map(f => f.trim()).filter(f => f.length > 0);
    if (photoFiles.length > 0) {
        const photo = await loadPhotoForPdf(photoFiles[0]);
        if (photo) {
            if (y + 4 + PDF_PHOTO_H > pageHeight - PDF_FOOTER_SAFE) { doc.addPage(); y = PDF_HEADER_Y; }

            // Fixed-size frame — same on every case
            doc.setDrawColor(205);
            doc.rect(x, y, PDF_PHOTO_W, PDF_PHOTO_H);

            // Scale the photo to FIT inside the frame (preserving aspect ratio) and center it —
            // never stretched, never cropped.
            const scale = Math.min(PDF_PHOTO_W / photo.width, PDF_PHOTO_H / photo.height);
            const imgWidth  = photo.width  * scale;
            const imgHeight = photo.height * scale;
            const imgX = x + (PDF_PHOTO_W - imgWidth) / 2;
            const imgY = y + (PDF_PHOTO_H - imgHeight) / 2;
            doc.addImage(photo.dataUrl, 'PNG', imgX, imgY, imgWidth, imgHeight);
            y += PDF_PHOTO_H + 4;

            // Little timestamp caption directly below the photo
            const capTimestamp = formatPdfTimestamp(r.report_date);
            doc.setFont('times', 'italic'); doc.setFontSize(7.5); doc.setTextColor(130);
            doc.text(capTimestamp ? `Reported: ${capTimestamp}` : 'Timestamp unavailable', x, y);
            doc.setTextColor(30);
            y += 5;

            if (photoFiles.length > 1) {
                y = addWrappedText(doc, `+ ${photoFiles.length - 1} more photo(s) attached to this case (see the app for the full set).`, x, y, maxWidth, pageHeight, { italic: true, size: 8, gray: 130 });
            }
        } else {
            y = addWrappedText(doc, 'Photo evidence on file could not be loaded for this export.', x, y, maxWidth, pageHeight, { italic: true, size: 8.5, gray: 150 });
        }
    } else {
        y = addWrappedText(doc, 'No photo evidence attached.', x, y, maxWidth, pageHeight, { italic: true, size: 8.5, gray: 130 });
    }
    y += 2;

    // ── 4. FIELD OBSERVATIONS ── (the reporter's original notes)
    sec++;
    y = addSectionHeader(doc, sec, 'Field Observations', x, y, maxWidth, pageHeight);
    y = addWrappedText(doc, r.description || 'No field observations recorded.', x, y, maxWidth, pageHeight, { italic: true, gray: r.description ? 30 : 130 });
    y += 2;

    // ── 5. RECOMMENDATION PROVIDED ── (RESOLVED cases only — a formal, summarized
    //     record of the treatment advice CASD staff sent to the farmer. This replaces
    //     the old full "Conversation with Farmer" thread: farmer replies are excluded
    //     on purpose so the export reads as an official case record, not a chat log.)
    if (isResolved) {
        sec++;
        y = addSectionHeader(doc, sec, 'Recommendation Provided', x, y, maxWidth, pageHeight);
        y = addRecommendationText(doc, r.messages, x, y, maxWidth, pageHeight);
        y += 2;
    }

    // ── REVIEWER REMARKS ── (kept for either status, only when present)
    if (r.remarks) {
        sec++;
        y = addSectionHeader(doc, sec, 'Office Notes', x, y, maxWidth, pageHeight);
        y = addWrappedText(doc, r.remarks, x, y, maxWidth, pageHeight);
    }

    y += 2;
    y = addSignatureBlock(doc, x, y, maxWidth, pageHeight);

    if (y > pageHeight - PDF_FOOTER_SAFE) { doc.addPage(); y = PDF_HEADER_Y; }
    doc.setDrawColor(225);
    doc.line(x, y, x + maxWidth, y);
    return y + 10;
}

function getJsPDF() {
    if (typeof window.jspdf === 'undefined' || !window.jspdf.jsPDF) {
        alert('PDF library is still loading — please wait a moment and try again.');
        return null;
    }
    return window.jspdf.jsPDF;
}

/* ═══════════════════════════════════════════════════
   PDF PREVIEW MODAL — every export opens as an in-app
   preview first (rendered from an in-memory blob, no
   file written to disk yet). The reviewer only gets an
   actual downloaded file if they click "Download PDF"
   inside the preview. See #pdfPreviewModal in reports.php
   for the markup this drives.
═══════════════════════════════════════════════════ */
window._pdfPreviewBlobUrl  = null;
window._pdfPreviewFilename = null;

// Renders the finished jsPDF document into the preview modal as a blob URL —
// nothing is written to the downloads folder at this point.
function showPdfPreview(doc, filename, title) {
    // Release any previous preview's blob URL first so repeated exports don't
    // leak memory over a long session.
    if (window._pdfPreviewBlobUrl) {
        URL.revokeObjectURL(window._pdfPreviewBlobUrl);
        window._pdfPreviewBlobUrl = null;
    }

    const blobUrl = URL.createObjectURL(doc.output('blob'));
    window._pdfPreviewBlobUrl  = blobUrl;
    window._pdfPreviewFilename = filename;

    const titleEl = document.getElementById('pdfPreviewTitle');
    if (titleEl) titleEl.innerText = title || 'Report Preview';

    const frame = document.getElementById('pdfPreviewFrame');
    if (frame) frame.src = blobUrl;

    const modal = document.getElementById('pdfPreviewModal');
    if (!modal) { console.error('pdfPreviewModal element not found in DOM'); return; }
    modal.classList.remove('hidden');

    const box = document.getElementById('pdfPreviewBox');
    if (box) {
        box.style.animation = 'none';
        box.offsetHeight;
        box.style.animation = 'reportPopIn .22s ease';
    }
}

// Closes the preview and frees the blob URL — the generated PDF is discarded
// unless "Download PDF" was clicked first.
function closePdfPreview() {
    const modal = document.getElementById('pdfPreviewModal');
    if (modal) modal.classList.add('hidden');

    const frame = document.getElementById('pdfPreviewFrame');
    if (frame) frame.src = 'about:blank';

    if (window._pdfPreviewBlobUrl) {
        URL.revokeObjectURL(window._pdfPreviewBlobUrl);
        window._pdfPreviewBlobUrl = null;
    }
    window._pdfPreviewFilename = null;
}

// "Download PDF" inside the preview — saves the exact file already on screen
// (no re-generation) as a real download.
function downloadPreviewedPdf() {
    if (!window._pdfPreviewBlobUrl || !window._pdfPreviewFilename) return;
    const a = document.createElement('a');
    a.href = window._pdfPreviewBlobUrl;
    a.download = window._pdfPreviewFilename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Escape closes just the preview (capture phase, with stopPropagation) so it
// doesn't also trigger the case-review modal's own Escape handling underneath
// it — e.g. closing the View Report modal the preview was opened on top of.
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    const modal = document.getElementById('pdfPreviewModal');
    if (modal && !modal.classList.contains('hidden')) {
        e.stopPropagation();
        closePdfPreview();
    }
}, true);

// Build the single case currently open in the modal, then show it as a preview
async function exportSingleReport() {
    const r = window._currentViewData;
    if (!r) return;
    const JsPDFCtor = getJsPDF();
    if (!JsPDFCtor) return;

    const btn = document.getElementById('view_export_btn');
    const btnOriginalHTML = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; btn.innerHTML = 'Preparing Preview\u2026'; }

    try {
        await loadLogoForPdf();
        const doc = new JsPDFCtor();
        await buildCaseFilePDF(doc, r, PDF_HEADER_Y);
        finalizeLetterheadedDoc(doc, 'Disease Case Report');
        showPdfPreview(doc, `report_${r.reference_id}.pdf`, `Case ${r.reference_id}`);
    } finally {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = btnOriginalHTML; }
    }
}

// Build every report in the currently active tab (Verified or Resolved) into
// one document, then show it as a preview. Each case always starts on its own
// fresh page — a case never continues on the same page as the previous report,
// regardless of length.
async function exportAllReports() {
    if (!currentTabReports || currentTabReports.length === 0) {
        alert('No reports to export in this tab.');
        return;
    }
    const JsPDFCtor = getJsPDF();
    if (!JsPDFCtor) return;

    const btn = document.getElementById('exportTabBtn');
    const btnOriginalHTML = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; btn.innerHTML = 'Preparing Preview\u2026'; }

    try {
        await loadLogoForPdf();
        const tabLabel = currentTabReports[0].status
            ? currentTabReports[0].status.charAt(0).toUpperCase() + currentTabReports[0].status.slice(1)
            : 'Reports';

        const doc = new JsPDFCtor();
        let y = PDF_HEADER_Y;

        for (let i = 0; i < currentTabReports.length; i++) {
            if (i > 0) { doc.addPage(); y = PDF_HEADER_Y; } // every case after the first starts on its own fresh page
            y = await buildCaseFilePDF(doc, currentTabReports[i], y);
        }
        finalizeLetterheadedDoc(doc, `${tabLabel} Disease Case Reports (${currentTabReports.length})`);

        showPdfPreview(doc, `disease_reports_${tabLabel.toLowerCase()}.pdf`, `${tabLabel} Reports (${currentTabReports.length})`);
    } finally {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = btnOriginalHTML; }
    }
}