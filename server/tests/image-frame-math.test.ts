import { createRequire } from "node:module";
import { describe, expect, it } from "vitest";

const require = createRequire(import.meta.url);
const { computeImageFrame } = require("../../app/modules/media/image-frame-math.js") as {
  computeImageFrame: (input: Record<string, unknown>) => {
    keepsFullImage: boolean;
    cutsEdges: boolean;
    source: { x: number; y: number; width: number; height: number };
    zoom: number;
  };
};

describe("cadrage des photographies", () => {
  it("conserve toute l'image au zoom initial", () => {
    const frame = computeImageFrame({
      imageWidth: 800,
      imageHeight: 1200,
      viewWidth: 360,
      viewHeight: 480,
      zoom: 1,
      panX: 0,
      panY: 0,
      mode: "person",
    });
    expect(frame.keepsFullImage).toBe(true);
    expect(frame.cutsEdges).toBe(false);
    expect(frame.source.x).toBe(0);
    expect(frame.source.y).toBe(0);
    expect(frame.source.width).toBe(800);
    expect(frame.source.height).toBe(1200);
  });

  it("ne recadre un logo qu'après un zoom explicite", () => {
    const initial = computeImageFrame({ imageWidth: 400, imageHeight: 200, viewWidth: 360, viewHeight: 180, zoom: 1, mode: "logo" });
    const zoomed = computeImageFrame({ imageWidth: 400, imageHeight: 200, viewWidth: 360, viewHeight: 180, zoom: 2, panX: 1, panY: 0, mode: "logo" });
    expect(initial.keepsFullImage).toBe(true);
    expect(initial.source.width).toBe(400);
    expect(zoomed.cutsEdges).toBe(true);
    expect(zoomed.source.width).toBeLessThan(400);
  });
});
