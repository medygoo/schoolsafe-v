(function (root) {
  "use strict";

  function open(options) {
    var mode = options && options.mode === "logo" ? "logo" : "person";
    var state = { zoom: 1, panX: 0, panY: 0, image: null, stream: null };
    var viewWidth = 360;
    var viewHeight = mode === "logo" ? 180 : 480;
    var modal = root.ssModal({
      title: mode === "logo" ? "Cadrage du logo" : "Cadrage de la photographie",
      content:
        '<div class="image-frame" data-image-frame>' +
          '<p class="ss-muted">' + (mode === "logo"
            ? "Le logo garde ses proportions. La transparence est conservée au format PNG."
            : "Le cadrage initial conserve toute la photographie, y compris les épaules et le tronc. Zoomez seulement si vous devez resserrer.") + '</p>' +
          '<video data-frame-video playsinline autoplay muted style="display:none;width:100%;max-height:240px;background:#111"></video>' +
          '<canvas data-frame-preview width="' + viewWidth + '" height="' + viewHeight + '" style="width:100%;max-width:420px;height:auto;background:#111;display:block"></canvas>' +
          '<label>Fichier<input data-frame-file type="file" accept="image/jpeg,image/png,image/webp"></label>' +
          '<button type="button" data-frame-camera>Prendre une photo</button>' +
          '<button type="button" data-frame-capture style="display:none">Utiliser cette image</button>' +
          '<label>Zoom<input data-frame-zoom type="range" min="1" max="3" step="0.01" value="1"></label>' +
          '<label>Horizontal<input data-frame-x type="range" min="-1" max="1" step="0.01" value="0"></label>' +
          '<label>Vertical<input data-frame-y type="range" min="-1" max="1" step="0.01" value="0"></label>' +
          '<button type="button" data-frame-reset>Réinitialiser</button>' +
        '</div>',
      actions: [
        { label: "Annuler", variant: "secondary", onClick: function () { stop(); modal.close(); } },
        { label: "Confirmer", variant: "primary", attrs: { "data-frame-confirm": "true" } }
      ]
    });
    var rootNode = document.querySelector("[data-image-frame]");
    if (!rootNode) return;
    var canvas = rootNode.querySelector("[data-frame-preview]");
    var video = rootNode.querySelector("[data-frame-video]");
    var context = canvas.getContext("2d");

    function stop() {
      if (state.stream) {
        state.stream.getTracks().forEach(function (track) { track.stop(); });
        state.stream = null;
      }
    }

    function draw() {
      context.clearRect(0, 0, viewWidth, viewHeight);
      if (!state.image) return;
      var frame = root.SchoolSafeImageFrameMath.computeImageFrame({
        imageWidth: state.image.naturalWidth || state.image.width,
        imageHeight: state.image.naturalHeight || state.image.height,
        viewWidth: viewWidth,
        viewHeight: viewHeight,
        zoom: state.zoom,
        panX: state.panX,
        panY: state.panY,
        mode: mode
      });
      context.drawImage(state.image, frame.offsetX, frame.offsetY, state.image.naturalWidth * frame.scale, state.image.naturalHeight * frame.scale);
    }

    function loadFile(file) {
      if (!file) return;
      var url = URL.createObjectURL(file);
      var image = new Image();
      image.onload = function () {
        state.image = image;
        state.zoom = 1;
        state.panX = 0;
        state.panY = 0;
        URL.revokeObjectURL(url);
        draw();
      };
      image.src = url;
    }

    rootNode.querySelector("[data-frame-file]").addEventListener("change", function (event) {
      loadFile(event.target.files && event.target.files[0]);
    });
    rootNode.querySelector("[data-frame-zoom]").addEventListener("input", function (event) {
      state.zoom = Number(event.target.value);
      draw();
    });
    rootNode.querySelector("[data-frame-x]").addEventListener("input", function (event) {
      state.panX = Number(event.target.value);
      draw();
    });
    rootNode.querySelector("[data-frame-y]").addEventListener("input", function (event) {
      state.panY = Number(event.target.value);
      draw();
    });
    rootNode.querySelector("[data-frame-reset]").addEventListener("click", function () {
      state.zoom = 1;
      state.panX = 0;
      state.panY = 0;
      rootNode.querySelector("[data-frame-zoom]").value = "1";
      rootNode.querySelector("[data-frame-x]").value = "0";
      rootNode.querySelector("[data-frame-y]").value = "0";
      draw();
    });
    rootNode.querySelector("[data-frame-camera]").addEventListener("click", function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        window.alert("La caméra n'est pas disponible sur cet appareil. Importez un fichier.");
        return;
      }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false }).then(function (stream) {
        state.stream = stream;
        video.srcObject = stream;
        video.style.display = "block";
        rootNode.querySelector("[data-frame-capture]").style.display = "inline-block";
      }).catch(function () {
        window.alert("La caméra n'est pas disponible. Importez un fichier.");
      });
    });
    rootNode.querySelector("[data-frame-capture]").addEventListener("click", function () {
      var shot = document.createElement("canvas");
      shot.width = video.videoWidth || 640;
      shot.height = video.videoHeight || 480;
      shot.getContext("2d").drawImage(video, 0, 0);
      var image = new Image();
      image.onload = function () {
        state.image = image;
        state.zoom = 1;
        draw();
        stop();
        video.style.display = "none";
      };
      image.src = shot.toDataURL("image/jpeg", 0.92);
    });

    var confirm = document.querySelector("[data-frame-confirm]");
    if (confirm) {
      confirm.addEventListener("click", function () {
        if (!state.image) {
          window.alert("Importez ou prenez la photographie avant de confirmer.");
          return;
        }
        var frame = root.SchoolSafeImageFrameMath.computeImageFrame({
          imageWidth: state.image.naturalWidth || state.image.width,
          imageHeight: state.image.naturalHeight || state.image.height,
          viewWidth: viewWidth,
          viewHeight: viewHeight,
          zoom: state.zoom,
          panX: state.panX,
          panY: state.panY,
          mode: mode
        });
        var output = document.createElement("canvas");
        var longSide = Math.max(frame.source.width, frame.source.height);
        var cap = mode === "logo" ? 512 : 1200;
        var ratio = Math.min(1, cap / longSide);
        output.width = Math.max(1, Math.round(frame.source.width * ratio));
        output.height = Math.max(1, Math.round(frame.source.height * ratio));
        output.getContext("2d").drawImage(
          state.image,
          frame.source.x, frame.source.y, frame.source.width, frame.source.height,
          0, 0, output.width, output.height
        );
        var mime = mode === "logo" ? "image/png" : "image/jpeg";
        output.toBlob(function (blob) {
          stop();
          modal.close();
          if (blob && typeof options.onConfirm === "function") options.onConfirm(blob, mime);
        }, mime, 0.92);
      });
    }
  }

  root.SchoolSafeImageFrame = { open: open };
})(window);
