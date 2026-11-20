"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("assets/js/app.js", "utf8");
const declaration = source.match(/^const safeImageURL=.*;$/m);
assert.ok(declaration, "safeImageURL declaration must exist in storefront source");

const safeImageURL = vm.runInNewContext(
  declaration[0] + "\nsafeImageURL",
  { URL, window: { location: { origin: "http://localhost" } } }
);

const cases = [
  ["same-origin local HTTP image", "/uploads/product.jpg", "http://localhost/uploads/product.jpg"],
  ["same-origin absolute local HTTP image", "http://localhost/media/item.png", "http://localhost/media/item.png"],
  ["secure external CDN image", "https://cdn.example.test/item.jpg", "https://cdn.example.test/item.jpg"],
  ["insecure external HTTP image", "http://cdn.example.test/item.jpg", ""],
  ["javascript URL", "javascript:alert(1)", ""],
  ["data URL", "data:image/svg+xml,<svg/>", ""],
  ["malformed URL", "http://[", ""],
];

for (const [label, input, expected] of cases) {
  assert.equal(safeImageURL(input), expected, label);
}

console.log(`Storefront image URL checks passed (${cases.length} cases).\n`);
