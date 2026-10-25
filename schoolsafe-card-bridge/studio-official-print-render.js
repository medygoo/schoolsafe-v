(function (root) {
  'use strict';
  let activeDialog = null;
  function safeAsset(value, base) {
    if (/^data:image\/(?:png|jpeg|webp|gif);base64,[a-z0-9+/=]+$/i.test(value)) return value;
    const url = new URL(value, base);
    if (url.origin !== new URL(base).origin || !['http:', 'https:'].includes(url.protocol)
        || url.username || url.password) throw new Error('PRINT_ASSET_ORIGIN');
    return url.href;
  }
  function credentialReady(result, student, school, qr) {
    const c = result?.input?.credential;
    if (result?.nativeReference?.studentId !== student || result.nativeReference.schoolId !== school
        || result.issuanceAllowed !== false || !Array.isArray(result.missing) || result.missing.length
        || !result.input.academicYearLabel || c?.status !== 'active' || !c.card_id
        || c.qr_payload !== qr || result.view?.student?.qr !== qr
        || qr !== 'schoolsafe://card/' + c.card_number + '/' + c.signature) throw new Error('PRINT_NOT_READY');
    return result;
  }
  function deadline(promise, milliseconds = 5000) {
    let timer;
    return Promise.race([promise, new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error('PRINT_ASSET_TIMEOUT')), milliseconds);
    })]).finally(() => clearTimeout(timer));
  }
  async function waitAssets(node, view) {
    const base = node.ownerDocument.baseURI;
    const loads = [];
    for (const image of node.querySelectorAll('img')) {
      safeAsset(image.getAttribute('src') || '', base);
      if (image.complete) {
        if (!image.naturalWidth || !image.naturalHeight) throw new Error('PRINT_IMAGE_MISSING');
      } else loads.push(new Promise((resolve, reject) => {
        image.addEventListener('load', () => image.naturalWidth && image.naturalHeight
          ? resolve() : reject(new Error('PRINT_IMAGE_MISSING')), { once: true });
        image.addEventListener('error', () => reject(new Error('PRINT_IMAGE_MISSING')), { once: true });
      }));
    }
    for (const element of [node, ...node.querySelectorAll('*')]) {
      const styles = [view.getComputedStyle(element)];
      for (const pseudo of ['::before', '::after']) styles.push(view.getComputedStyle(element, pseudo));
      for (const style of styles) {
        for (const property of ['backgroundImage', 'maskImage', 'borderImageSource', 'listStyleImage', 'content']) {
          const value = style[property] || '';
          const urls = value.matchAll(/url\((?:"([^"]*)"|'([^']*)'|([^)]*))\)/g);
          for (const match of urls) {
            const url = safeAsset(match[1] ?? match[2] ?? match[3], base);
            loads.push(new Promise((resolve, reject) => {
              const image = new view.Image();
              image.onload = () => image.naturalWidth ? resolve() : reject(new Error('PRINT_BACKGROUND_MISSING'));
              image.onerror = () => reject(new Error('PRINT_BACKGROUND_MISSING'));
              image.src = url;
            }));
          }
        }
      }
    }
    if (node.ownerDocument.fonts) loads.push(node.ownerDocument.fonts.ready.then(() => {
      for (const font of node.ownerDocument.fonts) if (font.status === 'error') throw new Error('PRINT_FONT_MISSING');
    }));
    await deadline(Promise.all(loads));
  }
  function copyPreview(source, targetDocument, qr) {
    const qrNodes = [...source.querySelectorAll('[id^="ss-qr-"]')];
    if (qrNodes.length !== 2 || qrNodes.some(node => node.title !== qr
        || !node.querySelector('canvas,img'))) throw new Error('PRINT_QR_NOT_RENDERED');
    const clone = source.cloneNode(true);
    for (const element of [clone, ...clone.querySelectorAll('*')]) {
      if (['SCRIPT', 'IFRAME', 'OBJECT', 'EMBED', 'LINK', 'STYLE', 'FORM', 'BUTTON', 'INPUT'].includes(element.tagName)) {
        element.remove(); continue;
      }
      for (const attribute of [...element.attributes]) {
        if (/^on/i.test(attribute.name) || ['srcdoc', 'href', 'xlink:href'].includes(attribute.name)) element.removeAttribute(attribute.name);
      }
      if (element.tagName === 'IMG') element.src = safeAsset(element.getAttribute('src') || '', source.ownerDocument.baseURI);
    }
    const originals = [...source.querySelectorAll('canvas')];
    for (const [index, canvas] of [...clone.querySelectorAll('canvas')].entries()) {
      const original = originals[index];
      if (!original?.width || !original.height) throw new Error('PRINT_CANVAS_MISSING');
      const pixels = original.getContext('2d').getImageData(0, 0, original.width, original.height).data;
      let variation = false;
      for (let offset = 4; offset < pixels.length; offset += 4) {
        if (pixels[offset + 3] && (pixels[offset] !== pixels[0]
            || pixels[offset + 1] !== pixels[1] || pixels[offset + 2] !== pixels[2])) { variation = true; break; }
      }
      if (!variation) throw new Error('PRINT_CANVAS_BLANK');
      const image = targetDocument.createElement('img');
      image.src = original.toDataURL('image/png');
      image.width = original.width; image.height = original.height;
      image.style.cssText = canvas.style.cssText;
      canvas.replaceWith(image);
    }
    clone.removeAttribute('id');
    return targetDocument.importNode(clone, true);
  }
  function close() { if (activeDialog) { activeDialog.close(); activeDialog.remove(); activeDialog = null; } }
  async function open({ result, qr, read, current }) {
    close();
    const source = root.document.getElementById('ss-card-studio');
    if (!source || !current()) throw new Error('PRINT_CONTEXT_CHANGED');
    // Existing preview schedules QR rendering after its synchronous template build.
    await deadline(new Promise((resolve, reject) => {
      const until = Date.now() + 2400;
      const check = () => {
        if (!current()) return resolve();
        if (Date.now() >= until) return reject(new Error('PRINT_QR_NOT_RENDERED'));
        const nodes = [...source.querySelectorAll('[id^="ss-qr-"]')];
        if (nodes.length === 2 && nodes.every(node => node.title === qr && node.querySelector('canvas,img'))) resolve();
        else setTimeout(check, 30);
      };
      check();
    }), 2500);
    if (!current()) throw new Error('PRINT_CONTEXT_CHANGED');
    await waitAssets(source, root);
    if (!current()) throw new Error('PRINT_CONTEXT_CHANGED');
    const css = [...root.document.styleSheets].map(sheet =>
      [...sheet.cssRules].map(rule => rule.cssText).join('\n')).join('\n');
    const dialog = root.document.createElement('dialog');
    dialog.style.cssText = 'width:min(1100px,94vw);height:90vh;padding:12px;border:1px solid #ccd3d8;border-radius:6px';
    const cancel = root.document.createElement('button'); cancel.textContent = 'Fermer';
    const print = root.document.createElement('button'); print.textContent = 'Imprimer / PDF'; print.disabled = true;
    const status = root.document.createElement('p'); status.setAttribute('role', 'status');
    const frame = root.document.createElement('iframe'); frame.title = 'Carte officielle recto et verso';
    frame.setAttribute('sandbox', 'allow-same-origin allow-modals');
    frame.style.cssText = 'width:100%;height:calc(100% - 100px);border:0;display:block';
    dialog.append(cancel, print, status, frame); root.document.body.append(dialog); activeDialog = dialog; dialog.showModal();
    cancel.addEventListener('click', close); dialog.addEventListener('cancel', close);
    const doc = frame.contentDocument;
    doc.open(); doc.write('<!doctype html><html lang="fr"><head><meta charset="utf-8"></head><body></body></html>'); doc.close();
    const policy = doc.createElement('meta'); policy.httpEquiv = 'Content-Security-Policy';
    policy.content = "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; base-uri 'self'; form-action 'none'";
    doc.head.append(policy);
    const base = doc.createElement('base'); base.href = root.document.baseURI; doc.head.append(base);
    doc.title = 'Carte officielle - recto et verso';
    const style = doc.createElement('style');
    style.textContent = css + '\nbody{margin:16px;background:white}@media print{body{margin:0}.ss-card-studio{overflow:visible}}';
    doc.head.append(style); doc.body.append(copyPreview(source, doc, qr));
    try {
      await waitAssets(doc.body, frame.contentWindow);
      if (!current() || activeDialog !== dialog) throw new Error('PRINT_CONTEXT_CHANGED');
      print.disabled = false;
      print.addEventListener('click', async () => {
        print.disabled = true;
        try {
          const fresh = await read();
          if (!current() || activeDialog !== dialog || JSON.stringify(fresh) !== JSON.stringify(result)) {
            throw new Error('PRINT_CONTEXT_CHANGED');
          }
          await waitAssets(doc.body, frame.contentWindow);
          if (!current() || activeDialog !== dialog) throw new Error('PRINT_CONTEXT_CHANGED');
          frame.contentWindow.focus(); frame.contentWindow.print();
          status.textContent = 'Dialogue navigateur demande. Impression physique non confirmee.';
        } catch {
          status.textContent = 'Dossier modifie ou asset inaccessible. Fermez puis relisez la carte.';
        }
      });
    } catch (error) { close(); throw error; }
  }
  const api = { safeAsset, credentialReady, waitAssets, copyPreview, open, close };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.SchoolSafeOfficialPrint = api;
})(typeof window === 'undefined' ? globalThis : window);
