(function (root, factory) {
  var api = factory();
  if (typeof module === "object" && module.exports) module.exports = api;
  root.SchoolSafeImageFrameMath = api;
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  function numberIn(value, min, max, fallback) {
    var parsed = Number(value);
    if (!isFinite(parsed)) return fallback;
    return Math.min(max, Math.max(min, parsed));
  }

  function computeImageFrame(input) {
    var imageWidth = numberIn(input && input.imageWidth, 1, 20000, 1);
    var imageHeight = numberIn(input && input.imageHeight, 1, 20000, 1);
    var viewWidth = numberIn(input && input.viewWidth, 1, 4000, 1);
    var viewHeight = numberIn(input && input.viewHeight, 1, 4000, 1);
    var zoom = numberIn(input && input.zoom, 1, 4, 1);
    var panX = numberIn(input && input.panX, -1, 1, 0);
    var panY = numberIn(input && input.panY, -1, 1, 0);
    var contain = Math.min(viewWidth / imageWidth, viewHeight / imageHeight);
    var scale = contain * zoom;
    var drawnWidth = imageWidth * scale;
    var drawnHeight = imageHeight * scale;
    var maxPanX = Math.max(0, (drawnWidth - viewWidth) / 2);
    var maxPanY = Math.max(0, (drawnHeight - viewHeight) / 2);
    var offsetX = (viewWidth - drawnWidth) / 2 + panX * maxPanX;
    var offsetY = (viewHeight - drawnHeight) / 2 + panY * maxPanY;
    var sourceX = Math.min(imageWidth, Math.max(0, -offsetX / scale));
    var sourceY = Math.min(imageHeight, Math.max(0, -offsetY / scale));
    var sourceWidth = Math.min(imageWidth - sourceX, viewWidth / scale);
    var sourceHeight = Math.min(imageHeight - sourceY, viewHeight / scale);
    return {
      mode: input && input.mode === "logo" ? "logo" : "person",
      zoom: zoom,
      panX: panX,
      panY: panY,
      scale: scale,
      offsetX: offsetX,
      offsetY: offsetY,
      source: { x: sourceX, y: sourceY, width: sourceWidth, height: sourceHeight },
      keepsFullImage: zoom === 1,
      cutsEdges: zoom > 1
    };
  }

  return { computeImageFrame: computeImageFrame };
});
