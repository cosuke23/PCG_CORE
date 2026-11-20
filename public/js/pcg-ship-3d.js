/*
 * BRP Gabriela Silang (OPV-8301) in 3D on the PCG HRMIS landing page
 * (resources/views/pages/pcg.blade.php, section #ship). Modelled from a photo of
 * the ship and her published particulars: OCEA OPV 270 Mk II, aluminium hull,
 * 84 m long, 16 m beam, 3.5 m draft, aft helideck with hangar, two 9.5 m RHIBs,
 * SharpEye X- and S-band radars (Wikipedia; Baird Maritime vessel review, 2020).
 * three.js is self-hosted (public/vendor/three-0.160.0, mapped by the page's
 * import map). Units: 1 = 10 m; x runs stern (-) to bow (+), y up, z across;
 * the waterline is y = 0.
 *
 * - Nothing is loaded until the section comes near the screen.
 * - The photo stays in place until the 3D ship has drawn its first frame;
 *   without WebGL, or if anything fails, the photo simply stays.
 * - Rendering pauses while the section is off screen or the tab is hidden.
 * - "Reduce motion": sea, flag, radars and ship hold still; dragging still turns the view.
 */
const stage = document.querySelector('[data-pcg-ship-3d]');

function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) {
        return false;
    }
}

if (stage && webglAvailable() && 'IntersectionObserver' in window) {
    const near = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            near.disconnect();
            start().catch((err) => { console.warn('3D ship not shown; keeping the photo.', err); });
        }
    }, { rootMargin: '300px 0px' });
    near.observe(stage);
}

async function start() {
    const THREE = await import('three');
    const { OrbitControls } = await import('three/addons/controls/OrbitControls.js');
    const { Sky } = await import('three/addons/objects/Sky.js');
    try { await document.fonts.load('800 100px Inter'); } catch (e) { /* system font fallback */ }
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Colours sampled from the photo.
    const C = {
        hull: '#f2f4f6', boot: '#20242b', deck: '#cfd4db', blueDeck: '#4a6fd0', glass: '#17212c',
        stripeBlue: '#0a3f86', stripeRed: '#c8102e', text: '#c8102e', number: '#15181d',
        mast: '#eef1f4', bay: '#353c46', water: '#0a2a6b', rhib: '#5a636d', orange: '#d9822b',
    };

    // ------------------------------------------------------------ renderer
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.6));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 0.6;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    const canvas = renderer.domElement;
    canvas.className = 'ph-ship__canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'BRP Gabriela Silang in 3D at sea. Drag to turn the view.');

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(26, 2.2, 0.1, 2000);

    // ------------------------------------------------------------ sky, sun, environment light
    const sun = new THREE.Vector3().setFromSphericalCoords(1, THREE.MathUtils.degToRad(90 - 52), THREE.MathUtils.degToRad(150));
    const sky = new Sky();
    sky.scale.setScalar(1000);
    sky.material.uniforms.turbidity.value = 1.8;
    sky.material.uniforms.rayleigh.value = 2.6;
    sky.material.uniforms.mieCoefficient.value = 0.0025;
    sky.material.uniforms.mieDirectionalG.value = 0.82;
    sky.material.uniforms.sunPosition.value.copy(sun);
    scene.add(sky);
    const pmrem = new THREE.PMREMGenerator(renderer);
    const envScene = new THREE.Scene();
    const envSky = new Sky(); envSky.scale.setScalar(1000);
    Object.keys(sky.material.uniforms).forEach((k) => { envSky.material.uniforms[k].value = sky.material.uniforms[k].value; });
    envScene.add(envSky);
    scene.environment = pmrem.fromScene(envScene).texture;
    scene.environmentIntensity = 0.9;

    const sunLight = new THREE.DirectionalLight('#fff3df', 3.2);
    sunLight.position.copy(sun).multiplyScalar(30);
    sunLight.castShadow = true;
    sunLight.shadow.mapSize.set(2048, 2048);
    Object.assign(sunLight.shadow.camera, { left: -6, right: 6, top: 5, bottom: -5, near: 1, far: 80 });
    sunLight.shadow.bias = -0.0005;
    sunLight.shadow.normalBias = 0.02;
    scene.add(sunLight);

    // ------------------------------------------------------------ ocean (reflective water with a generated wave texture)
    function waveNormals(size = 256) {
        // tileable height field from waves with whole-number frequencies, turned into a normal map
        const waves = [];
        for (let i = 0; i < 14; i++) waves.push({ fx: Math.round(Math.random() * 10 - 5), fy: Math.round(Math.random() * 10 - 5) || 1, a: 1 / (1 + i * 0.6), p: Math.random() * Math.PI * 2 });
        const h = new Float32Array(size * size);
        for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
            let v = 0;
            for (const w of waves) v += w.a * Math.sin(2 * Math.PI * (w.fx * x + w.fy * y) / size + w.p);
            h[y * size + x] = v;
        }
        const c = document.createElement('canvas'); c.width = c.height = size;
        const g = c.getContext('2d'), img = g.createImageData(size, size);
        const at = (x, y) => h[((y + size) % size) * size + ((x + size) % size)];
        for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
            const dx = (at(x + 1, y) - at(x - 1, y)) * 0.9, dy = (at(x, y + 1) - at(x, y - 1)) * 0.9;
            const n = new THREE.Vector3(-dx, -dy, 1).normalize();
            const i = (y * size + x) * 4;
            img.data[i] = (n.x * 0.5 + 0.5) * 255; img.data[i + 1] = (n.y * 0.5 + 0.5) * 255; img.data[i + 2] = (n.z * 0.5 + 0.5) * 255; img.data[i + 3] = 255;
        }
        g.putImageData(img, 0, 0);
        const t = new THREE.CanvasTexture(c);
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        return t;
    }
    // deep navy sea: physically lit surface, two wave layers drifting at different speeds,
    // sky reflections from the environment and glints from the sun
    const waveTex = waveNormals();
    waveTex.repeat.set(70, 70);
    const swellTex = waveNormals();
    swellTex.repeat.set(18, 18);
    const water = new THREE.Mesh(new THREE.PlaneGeometry(400, 400), new THREE.MeshPhysicalMaterial({
        color: C.water, roughness: 0.16, metalness: 0, normalMap: waveTex, normalScale: new THREE.Vector2(0.9, 0.9),
        clearcoat: 1, clearcoatRoughness: 0.28, clearcoatNormalMap: swellTex, clearcoatNormalScale: new THREE.Vector2(0.6, 0.6),
        envMapIntensity: 0.55,
    }));
    water.rotation.x = -Math.PI / 2;
    water.receiveShadow = true;
    scene.add(water);

    // ------------------------------------------------------------ helpers
    const ship = new THREE.Group();
    ship.name = 'BRP Gabriela Silang';
    scene.add(ship);
    const mat = (color, extra = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.45, metalness: 0.05, ...extra });
    const paint = (color, extra = {}) => new THREE.MeshPhysicalMaterial({ color, roughness: 0.36, metalness: 0, clearcoat: 0.35, clearcoatRoughness: 0.25, ...extra });
    const add = (geometry, material, parent = ship) => {
        const m = new THREE.Mesh(geometry, material);
        m.castShadow = true; m.receiveShadow = true;
        parent.add(m); return m;
    };
    const boxAt = (x0, x1, y0, y1, width, material, z = 0, parent = ship) => {
        const m = add(new THREE.BoxGeometry(x1 - x0, y1 - y0, width), material, parent);
        m.position.set((x0 + x1) / 2, (y0 + y1) / 2, z);
        return m;
    };
    // side profile (x, y) extruded across the ship, softly bevelled, centred
    const block = (points, width, material, bevel = 0.012, parent = ship) => {
        const s = new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x, y)));
        const g = new THREE.ExtrudeGeometry(s, { depth: width - bevel * 2, bevelEnabled: bevel > 0, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 2 });
        g.translate(0, 0, -(width - bevel * 2) / 2);
        return add(g, material, parent);
    };
    const canvasTex = (w, h, draw, srgb = true) => {
        const c = document.createElement('canvas'); c.width = w; c.height = h;
        draw(c.getContext('2d'), w, h);
        const t = new THREE.CanvasTexture(c);
        if (srgb) t.colorSpace = THREE.SRGBColorSpace;
        t.anisotropy = renderer.capabilities.getMaxAnisotropy();
        return t;
    };

    // ------------------------------------------------------------ hull (lofted cross-sections, real proportions)
    const X0 = -4.2, X1 = 4.22;                       // 84 m
    const deckY = (x) => {
        if (x < -0.76) return 0.48;                   // aft working deck / helideck level
        if (x < -0.72) return 0.48 + (x + 0.76) / 0.04 * 0.36;
        if (x < 2.6) return 0.84 + (x + 0.72) / 3.32 * 0.06;
        return 0.9 + Math.pow((x - 2.6) / (X1 - 2.6), 2) * 0.2;   // sheer rises to the bow
    };
    const keelY = (x) => {
        if (x < -3.5) return -0.35 + (-3.5 - x) / 0.7 * 0.17;       // run up to the transom
        if (x < 2.8) return -0.35;                                  // 3.5 m draft
        return -0.35 + Math.pow((x - 2.8) / (X1 - 2.8), 1.6) * 1.45; // raked stem
    };
    const halfBeam = (x) => {                         // 16 m beam
        if (x < -3.2) return 0.8 - Math.pow((-3.2 - x) / 1.0, 2) * 0.05;
        if (x < 0.9) return 0.8;
        return Math.max(0.004, 0.8 * (1 - Math.pow((x - 0.9) / (X1 - 0.9), 2.05)));
    };
    const bowness = (x) => Math.pow(THREE.MathUtils.clamp((x - 0.6) / 3.4, 0, 1), 1.2); // flare grows toward the bow
    const STATIONS = 180;
    const hullPos = [], hullUv = [], hullIdx = [];
    const uvOf = (x, y) => [(x - X0) / (X1 - X0), (y + 0.4) / 1.6];
    let ringCount = 0;
    for (let i = 0; i <= STATIONS; i++) {
        const x = X0 + (X1 - X0) * i / STATIONS;
        const top = deckY(x), bot = Math.min(keelY(x), top - 0.001), w = halfBeam(x), b = bowness(x);
        const yAt = (y) => Math.min(top, Math.max(bot, y));
        const half = [
            [w, top],
            [w * 0.995, yAt(top - 0.14)],
            [w * THREE.MathUtils.lerp(0.98, 0.78, b), yAt(0.22)],
            [w * THREE.MathUtils.lerp(0.96, 0.6, b), yAt(0.02)],
            [w * THREE.MathUtils.lerp(0.86, 0.42, b), yAt(-0.18)],
            [w * THREE.MathUtils.lerp(0.5, 0.18, b), yAt(-0.31)],
            [0, bot],
        ];
        const ring = [...half, ...half.slice(0, -1).reverse().map(([z, y]) => [-z, y])];
        ringCount = ring.length;
        ring.forEach(([z, y]) => { hullPos.push(x, y, z); hullUv.push(...uvOf(x, y)); });
    }
    for (let i = 0; i < STATIONS; i++) for (let k = 0; k < ringCount - 1; k++) {
        const a = i * ringCount + k, b2 = a + 1, c = a + ringCount, d = c + 1;
        hullIdx.push(a, c, b2, b2, c, d);
    }
    const deckStart = hullPos.length / 3;
    for (let i = 0; i <= STATIONS; i++) {
        const x = X0 + (X1 - X0) * i / STATIONS, y = deckY(x) + 0.002, w = halfBeam(x);
        hullPos.push(x, y, w, x, y, -w);
        hullUv.push(...uvOf(x, 1.15), ...uvOf(x, 1.15));
    }
    for (let i = 0; i < STATIONS; i++) { const a = deckStart + i * 2; hullIdx.push(a, a + 1, a + 2, a + 1, a + 3, a + 2); }
    for (let k = 1; k < ringCount - 1; k++) hullIdx.push(0, k + 1, k); // transom
    const hullGeo = new THREE.BufferGeometry();
    hullGeo.setAttribute('position', new THREE.Float32BufferAttribute(hullPos, 3));
    hullGeo.setAttribute('uv', new THREE.Float32BufferAttribute(hullUv, 2));
    hullGeo.setIndex(hullIdx);
    hullGeo.computeVertexNormals();

    // hull paint: white, dark boot-top, bow stripes, dark accent lines (as in the photo)
    const hullTex = canvasTex(4096, 780, (g, W, H) => {
        const px = (x, y) => { const [u, v] = uvOf(x, y); return [u * W, (1 - v) * H]; };
        g.fillStyle = C.hull; g.fillRect(0, 0, W, H);
        const [, yBoot] = px(0, 0.06); g.fillStyle = C.boot; g.fillRect(0, yBoot, W, H - yBoot);
        const poly = (pts, color) => {
            g.beginPath(); pts.forEach(([x, y], i) => { const [a, b] = px(x, y); i ? g.lineTo(a, b) : g.moveTo(a, b); });
            g.closePath(); g.fillStyle = color; g.fill();
        };
        poly([[1.22, 0.06], [1.36, 0.06], [2.25, 1.2], [2.11, 1.2]], C.stripeBlue);
        poly([[1.46, 0.06], [1.78, 0.06], [2.67, 1.2], [2.35, 1.2]], C.stripeRed);
        g.strokeStyle = C.boot; g.lineCap = 'round';
        const line = (a, b, c2, d, wpx) => { const [x1, y1] = px(a, b), [x2, y2] = px(c2, d); g.lineWidth = wpx; g.beginPath(); g.moveTo(x1, y1); g.lineTo(x2, y2); g.stroke(); };
        line(-0.72, 0.6, 2.0, 0.64, 7);              // line under the boat bays
        line(-3.6, 0.3, -1.3, 0.3, 7);               // aft hull line
        [-2.15, -1.85, -1.55].forEach((x) => line(x, 0.12, x + 0.32, 0.46, 9)); // slanted aft accents
        // portholes forward
        g.fillStyle = '#2a3340';
        for (let x = 2.9; x <= 3.55; x += 0.13) { const [a, b] = px(x, 0.8); g.beginPath(); g.arc(a, b, 7, 0, Math.PI * 2); g.fill(); }
    });
    add(hullGeo, paint('#ffffff', { map: hullTex, side: THREE.DoubleSide }));

    // words on both sides (separate decals, laid along the hull's taper, never mirrored)
    function decal(text, { font, color, width, height, x, y }) {
        const tex = canvasTex(1024, Math.round(1024 * height / width), (g, cw, ch) => {
            g.font = font.replace('{s}', String(Math.round(ch * 0.8)));
            g.fillStyle = color; g.textAlign = 'center'; g.textBaseline = 'middle';
            const chars = [...text], widths = chars.map((k) => g.measureText(k).width);
            const sum = widths.reduce((a, b) => a + b, 0), gap = Math.max(0, (cw * 0.98 - sum) / Math.max(1, chars.length - 1));
            let cx = (cw - (sum + gap * (chars.length - 1))) / 2;
            chars.forEach((k, i) => { g.fillText(k, cx + widths[i] / 2, ch / 2); cx += widths[i] + gap; });
        });
        const m = new THREE.MeshStandardMaterial({ map: tex, transparent: true, roughness: 0.4, polygonOffset: true, polygonOffsetFactor: -2 });
        const slope = (halfBeam(x + 0.01) - halfBeam(x - 0.01)) / 0.02;
        const z = halfBeam(x) + 0.006;
        [1, -1].forEach((side) => {
            const p = new THREE.Mesh(new THREE.PlaneGeometry(width, height), m);
            p.position.set(x, y, side * z);
            p.rotation.y = side > 0 ? -Math.atan(slope) : Math.PI + Math.atan(slope);
            ship.add(p);
        });
    }
    decal('PHILIPPINE COAST GUARD', { font: '700 {s}px Inter, Arial, sans-serif', color: C.text, width: 1.95, height: 0.17, x: -0.28, y: 0.44 });
    decal('8301', { font: '800 {s}px Inter, Arial, sans-serif', color: C.number, width: 0.5, height: 0.19, x: 3.12, y: 0.58 });

    // ------------------------------------------------------------ superstructure
    const white = paint(C.hull);
    const blue = mat(C.blueDeck, { roughness: 0.7 });
    const dark = mat(C.bay, { roughness: 0.75 });
    const glass = mat(C.glass, { roughness: 0.08, metalness: 0.7, envMapIntensity: 1.4 });
    const SW = 1.5;                                   // superstructure width, with narrow side walkways
    // aft block (hangar), roof over the boat bays, forward block with a raked front
    block([[-1.26, 0.48], [-0.62, 0.48], [-0.62, 1.42], [-1.26, 1.42]], SW, white);
    block([[-0.62, 1.2], [0.4, 1.2], [0.4, 1.42], [-0.62, 1.42]], SW, white);
    block([[0.4, 0.84], [2.05, 0.84], [1.82, 1.42], [0.4, 1.42]], SW, white);
    // hangar door on the aft face, toward the helideck
    boxAt(-1.275, -1.262, 0.52, 1.24, 0.92, mat('#aeb6c0', { roughness: 0.6, metalness: 0.3 }));
    for (let y = 0.58; y < 1.24; y += 0.06) boxAt(-1.279, -1.276, y, y + 0.006, 0.9, mat('#8b949e'));
    // boat bays: a dark tunnel through the ship, one 9.5 m RHIB on each side
    boxAt(-0.62, 0.4, 0.84, 1.2, 0.9, dark);
    boxAt(-0.62, 0.4, 0.48, 0.84, 0.9, dark);
    [1, -1].forEach((s) => {
        const p = new THREE.Mesh(new THREE.PlaneGeometry(1.02, 0.72), mat('#262c34', { roughness: 0.9 }));
        p.position.set(-0.11, 0.84, s * 0.451); if (s < 0) p.rotation.y = Math.PI; ship.add(p);
        const rhib = new THREE.Group(); ship.add(rhib);
        const tube = add(new THREE.CapsuleGeometry(0.075, 0.8, 8, 16), mat(C.rhib, { roughness: 0.75 }), rhib);
        tube.rotation.z = Math.PI / 2; tube.scale.set(1, 1, 1.9);
        boxAt(-0.34, 0.3, 0.06, 0.16, 0.2, mat('#2c3238'), 0, rhib);
        boxAt(-0.05, 0.12, 0.16, 0.26, 0.16, mat('#d8dde3'), 0, rhib); // console
        rhib.position.set(-0.11, 0.9, s * 0.62);
        // davit arm over each boat
        const davit = add(new THREE.BoxGeometry(0.04, 0.04, 0.32), mat('#e6e9ed', { metalness: 0.4 }));
        davit.position.set(-0.11, 1.16, s * 0.6);
    });
    // windows: rows on the superstructure sides, a wrap-around band on the bridge
    const windowRow = (n) => canvasTex(512, 64, (g, w, h) => {
        g.clearRect(0, 0, w, h);
        const pad = 10, cell = (w - pad * 2) / n;
        g.fillStyle = C.glass;
        for (let i = 0; i < n; i++) g.fillRect(pad + i * cell + 6, 14, cell - 12, h - 28);
    });
    const winMat = (n) => new THREE.MeshStandardMaterial({ map: windowRow(n), transparent: true, roughness: 0.1, metalness: 0.6, polygonOffset: true, polygonOffsetFactor: -2 });
    [1, -1].forEach((s) => {
        [[0.62, 1.7, 1.02, 0.14, 7], [-1.18, -0.68, 1.02, 0.14, 2]].forEach(([x0, x1, y, h, n]) => {
            const p = new THREE.Mesh(new THREE.PlaneGeometry(x1 - x0, h), winMat(n));
            p.position.set((x0 + x1) / 2, y, s * (SW / 2 + 0.002)); if (s < 0) p.rotation.y = Math.PI; ship.add(p);
        });
    });
    // roof decks painted blue, with railings
    boxAt(-1.24, -0.88, 1.42, 1.44, SW - 0.05, blue);
    boxAt(1.02, 1.82, 1.42, 1.44, SW - 0.12, blue);
    // bridge: deck, glass band with mullions, roof and wings
    block([[-0.88, 1.42], [1.02, 1.42], [0.9, 1.84], [-0.82, 1.84]], 1.34, white);
    const mullions = canvasTex(1024, 64, (g, w, h) => {
        g.fillStyle = C.glass; g.fillRect(0, 0, w, h);
        g.fillStyle = '#c9d1db'; for (let x = 0; x < w; x += 46) g.fillRect(x, 0, 4, h);
        const grd = g.createLinearGradient(0, 0, 0, h); grd.addColorStop(0, 'rgba(255,255,255,0.18)'); grd.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = grd; g.fillRect(0, 0, w, h);
    });
    block([[-0.85, 1.6], [0.975, 1.6], [0.935, 1.76], [-0.83, 1.76]], 1.36, mat('#ffffff', { map: mullions, roughness: 0.08, metalness: 0.6 }), 0.004);
    boxAt(-0.86, 0.95, 1.84, 1.86, 1.36, blue);
    [1, -1].forEach((s) => boxAt(0.55, 0.98, 1.66, 1.82, 0.12, white, s * 0.73)); // bridge wings
    // forecastle deck (blue) at the bow
    const fore = add(new THREE.BoxGeometry(1.0, 0.02, 1.02), blue);
    fore.position.set(2.5, deckY(2.5) + 0.018, 0);
    // helideck with its circle marking (aft), and the low working deck at the stern
    const heliTex = canvasTex(1024, 512, (g, w, h) => {
        g.fillStyle = '#c3c9d1'; g.fillRect(0, 0, w, h);
        g.strokeStyle = '#f7f8fa'; g.lineWidth = 16; g.beginPath(); g.arc(w / 2, h / 2, 170, 0, Math.PI * 2); g.stroke();
        g.lineWidth = 10; g.strokeRect(24, 24, w - 48, h - 48);
        g.strokeStyle = '#f2c94c'; g.lineWidth = 8; g.beginPath(); g.arc(w / 2, h / 2, 120, 0, Math.PI * 2); g.stroke();
    });
    const heliSide = mat('#c3c9d1');
    const heli = add(new THREE.BoxGeometry(2.0, 0.06, 1.5), [heliSide, heliSide, mat('#ffffff', { map: heliTex, roughness: 0.8 }), heliSide, heliSide, heliSide]);
    heli.position.set(-2.28, 0.56, 0);
    boxAt(-4.18, -3.3, 0.48, 0.5, 1.5, blue);
    boxAt(-3.85, -3.55, 0.5, 0.72, 0.32, mat(C.orange, { roughness: 0.6 }), 0.32);     // deck crane base
    const jib = add(new THREE.BoxGeometry(0.5, 0.04, 0.04), mat(C.orange)); jib.position.set(-3.55, 0.78, 0.32); jib.rotation.z = 0.35;
    [-0.5, -0.36, 0.5].forEach((z) => { // life-raft canisters
        const raft = add(new THREE.CylinderGeometry(0.06, 0.06, 0.2, 16), white); raft.rotation.z = Math.PI / 2; raft.position.set(-4.0, 0.58, z);
    });
    // railings (instanced posts)
    const rail = mat('#e6eaee', { roughness: 0.35, metalness: 0.5 });
    const posts = [];
    const railRun = (x0, x1, y, z) => [1, -1].forEach((s) => {
        boxAt(x0, x1, y - 0.006, y + 0.006, 0.012, rail, s * z);
        for (let x = x0; x <= x1 + 1e-6; x += 0.16) posts.push([x, y - 0.05, s * z]);
    });
    railRun(-3.26, -1.3, 0.69, 0.74);
    railRun(-4.15, -3.32, 0.6, 0.74);
    railRun(-1.24, -0.88, 1.52, 0.72);
    railRun(1.02, 1.82, 1.52, 0.69);
    railRun(2.05, 3.0, deckY(2.5) + 0.12, 0.5);
    const postMesh = new THREE.InstancedMesh(new THREE.BoxGeometry(0.01, 0.1, 0.01), rail, posts.length);
    posts.forEach(([x, y, z], i) => postMesh.setMatrixAt(i, new THREE.Matrix4().makeTranslation(x, y, z)));
    postMesh.castShadow = true; ship.add(postMesh);

    // ------------------------------------------------------------ mast, radars, domes, flag
    const mastMat = paint(C.mast);
    const mast = add(new THREE.CylinderGeometry(0.05, 0.085, 1.15, 4), mastMat);
    mast.rotation.y = Math.PI / 4; mast.position.set(-0.22, 2.42, 0);
    [[2.34, 0.34], [2.7, 0.26]].forEach(([y, d]) => boxAt(-0.22 - d / 2, -0.22 + d / 2, y - 0.012, y + 0.012, d, mastMat)); // platforms
    [[2.5, 0.66], [2.82, 0.44]].forEach(([y, w]) => { const yard = add(new THREE.BoxGeometry(0.03, 0.03, w), mastMat); yard.position.set(-0.22, y, 0); });
    const radarMat = mat('#f4f6f8', { roughness: 0.5 });
    const sBand = new THREE.Group(); sBand.position.set(-0.22, 2.38, 0); ship.add(sBand);   // S-band antenna (larger)
    add(new THREE.BoxGeometry(0.05, 0.05, 0.62), radarMat, sBand).position.y = 0.04;
    const xBand = new THREE.Group(); xBand.position.set(-0.22, 2.74, 0); ship.add(xBand);   // X-band antenna
    add(new THREE.BoxGeometry(0.035, 0.035, 0.4), radarMat, xBand).position.y = 0.03;
    const domeMat = paint('#f6f7f9');
    add(new THREE.SphereGeometry(0.075, 24, 16), domeMat).position.set(-0.22, 3.06, 0);
    [[0.6, 1.94, 0.42], [0.6, 1.94, -0.42], [-0.6, 1.94, 0]].forEach(([x, y, z]) => {    // satcom radomes on the bridge roof
        add(new THREE.SphereGeometry(0.07, 20, 14), domeMat).position.set(x, y, z);
        boxAt(x - 0.03, x + 0.03, 1.86, y - 0.02, 0.06, mastMat, z);
    });
    [[-0.05, 0.5], [-0.4, -0.5]].forEach(([x, z]) => { // whip antennas
        const whip = add(new THREE.CylinderGeometry(0.004, 0.006, 0.6, 6), mastMat); whip.position.set(x, 2.16, z);
    });
    // searchlights at the bridge front
    [1, -1].forEach((s) => add(new THREE.CylinderGeometry(0.025, 0.03, 0.05, 12), mat('#2b3138')).position.set(0.92, 1.9, s * 0.4));
    // gaff angled aft, Philippine flag at its end
    const gaff = add(new THREE.CylinderGeometry(0.01, 0.01, 0.75, 8), mastMat);
    gaff.position.set(-0.53, 2.56, 0); gaff.rotation.z = Math.PI / 2 - 0.35;
    const flagTex = canvasTex(512, 256, (g) => {
        g.fillStyle = '#0038a8'; g.fillRect(0, 0, 512, 128);
        g.fillStyle = '#ce1126'; g.fillRect(0, 128, 512, 128);
        g.fillStyle = '#ffffff'; g.beginPath(); g.moveTo(0, 0); g.lineTo(222, 128); g.lineTo(0, 256); g.closePath(); g.fill();
        g.fillStyle = '#fcd116'; g.beginPath(); g.arc(74, 128, 24, 0, Math.PI * 2); g.fill();
        for (let i = 0; i < 8; i++) { g.save(); g.translate(74, 128); g.rotate(i * Math.PI / 4); g.beginPath(); g.moveTo(26, -5); g.lineTo(46, 0); g.lineTo(26, 5); g.closePath(); g.fill(); g.restore(); }
        [[18, 26], [18, 230], [190, 128]].forEach(([x, y]) => { g.beginPath(); g.arc(x, y, 9, 0, Math.PI * 2); g.fill(); });
    });
    const flagGeo = new THREE.PlaneGeometry(0.56, 0.28, 24, 6);
    flagGeo.translate(-0.28, 0, 0);
    const flagBase = flagGeo.attributes.position.array.slice();
    const flag = new THREE.Mesh(flagGeo, new THREE.MeshStandardMaterial({ map: flagTex, side: THREE.DoubleSide, roughness: 0.85 }));
    flag.castShadow = true; flag.position.set(-0.84, 2.54, 0); ship.add(flag);

    // ------------------------------------------------------------ wake and bow waves
    const foamTex = canvasTex(1024, 256, (g, w, h) => {
        for (let i = 0; i < 5200; i++) {
            const x = Math.random() * w, spread = 18 + x * 0.2, y = h / 2 + (Math.random() - 0.5) * spread;
            const a = Math.max(0, 0.95 - x / (w * 1.05)) * (0.4 + Math.random() * 0.6);
            g.fillStyle = `rgba(255,255,255,${a.toFixed(3)})`;
            g.beginPath(); g.arc(x, y, 0.8 + Math.random() * 2.6, 0, Math.PI * 2); g.fill();
        }
    }, false);
    foamTex.wrapS = THREE.RepeatWrapping;
    const foam = new THREE.MeshBasicMaterial({ map: foamTex, transparent: true, depthWrite: false, opacity: 0.85 });
    const wake = new THREE.Mesh(new THREE.PlaneGeometry(9, 2.0), foam);
    wake.rotation.x = -Math.PI / 2; wake.rotation.z = Math.PI; wake.position.set(-8.7, 0.012, 0); scene.add(wake);
    const bowFoam = [];
    [1, -1].forEach((s) => {
        const w = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 0.55), foam);
        w.rotation.x = -Math.PI / 2; w.rotation.z = Math.PI + s * 0.1; w.position.set(1.7, 0.014, s * 0.9);
        scene.add(w); bowFoam.push(w);
    });

    // ------------------------------------------------------------ camera framing (ship fills the stage at any shape)
    const target = new THREE.Vector3(0.15, 0.78, 0);
    const viewDir = new THREE.Vector3(0.3, 0.62, 1).normalize(); // elevated, like the aerial photo
    const controls = new OrbitControls(camera, canvas);
    controls.target.copy(target);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.enablePan = false;
    controls.rotateSpeed = 0.55;
    controls.minPolarAngle = 0.5;
    controls.maxPolarAngle = Math.PI * 0.47;
    canvas.style.touchAction = 'pan-y';
    let framed = false;
    const frame = () => {
        // distance at which the 84 m hull (plus margin) spans the stage width
        const hfov = 2 * Math.atan(Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camera.aspect);
        const need = 10.2 / (2 * Math.tan(hfov / 2));
        const dist = Math.max(need, 8.2);
        if (!framed) { camera.position.copy(target).addScaledVector(viewDir, dist); framed = true; }
        else { const d = camera.position.clone().sub(controls.target).setLength(dist); camera.position.copy(controls.target).add(d); }
        controls.update();
    };
    const resize = () => {
        const w = stage.clientWidth, h = stage.clientHeight || Math.round(w * 0.45);
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        frame();
    };
    resize();
    if ('ResizeObserver' in window) new ResizeObserver(resize).observe(stage);
    else window.addEventListener('resize', resize);

    let onScreen = true;
    new IntersectionObserver((entries) => { onScreen = entries.some((e) => e.isIntersecting); }).observe(stage);

    const flagPos = flagGeo.attributes.position;
    function animate(t) {
        waveTex.offset.set(t * 0.012, t * 0.007);
        swellTex.offset.set(-t * 0.004, t * 0.006);
        for (let i = 0; i < flagPos.count; i++) {
            const x = flagBase[i * 3], y = flagBase[i * 3 + 1], k = -x / 0.56;
            flagPos.setZ(i, Math.sin(x * 14 + t * 7) * 0.035 * k + Math.sin(y * 9 + t * 5) * 0.01 * k);
        }
        flagPos.needsUpdate = true; flagGeo.computeVertexNormals();
        sBand.rotation.y = t * 1.6;
        xBand.rotation.y = -t * 2.4;
        ship.position.y = Math.sin(t * 0.8) * 0.018;
        ship.rotation.x = Math.sin(t * 0.6) * 0.016;
        ship.rotation.z = Math.sin(t * 0.47) * 0.007;
        foamTex.offset.x = (t * 0.05) % 1;
    }

    // first frame now, then swap the photo for the canvas (it fades in)
    animate(0);
    renderer.render(scene, camera);
    stage.appendChild(canvas);
    void canvas.offsetWidth;
    stage.classList.add('is-3d');

    const clock = new THREE.Clock();
    renderer.setAnimationLoop(() => {
        if (!onScreen || document.hidden) return;
        if (!reduce) animate(clock.getElapsedTime());
        controls.update();
        renderer.render(scene, camera);
    });
}
