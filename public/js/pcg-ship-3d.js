/*
 * BRP Gabriela Silang (OPV-8301) in 3D on the PCG HRMIS landing page
 * (resources/views/pages/pcg.blade.php, section #ship), modelled from a photo
 * with three.js (self-hosted: public/vendor/three-0.160.0, mapped by the
 * page's import map). Units: 1 = 10 m; x runs stern to bow, y up, z across.
 *
 * - Nothing is loaded until the section comes near the screen.
 * - The photo stays in place until the 3D ship has drawn its first frame;
 *   without WebGL, or if anything fails, the photo simply stays.
 * - Rendering pauses while the section is off screen or the tab is hidden.
 * - "Reduce motion": the sea and the ship hold still; dragging still turns the view.
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
    const { RoomEnvironment } = await import('three/addons/environments/RoomEnvironment.js');
    try { await document.fonts.load('800 100px Inter'); } catch (e) { /* system font fallback */ }
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Colours sampled from the photo.
    const C = {
        hull: '#f3f5f7', boot: '#23272e', deck: '#d7dbe1', blueDeck: '#4f74d6', glass: '#1d2733',
        stripeBlue: '#0a3f86', stripeRed: '#c8102e', text: '#c8102e', number: '#1a1d22',
        mast: '#eef1f4', bay: '#3a414b', sea: '#2b3f7a', rhib: '#5d6670',
    };

    // ------------------------------------------------------------ renderer, scene, camera
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.setClearColor(0x000000, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    const canvas = renderer.domElement;
    canvas.className = 'ph-ship__canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'BRP Gabriela Silang in 3D. Drag to turn the view.');

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.7;

    const camera = new THREE.PerspectiveCamera(28, 16 / 10, 0.1, 100);
    camera.position.set(3.4, 6.2, 10.6);
    const controls = new OrbitControls(camera, canvas);
    controls.target.set(0.25, 0.7, 0);
    controls.enableDamping = true;
    controls.enableZoom = false;
    controls.enablePan = false;
    controls.rotateSpeed = 0.6;
    controls.minPolarAngle = 0.35;
    controls.maxPolarAngle = Math.PI * 0.46;   // never under the sea
    controls.update();
    canvas.style.touchAction = 'pan-y';        // phones: sideways drag turns, swipe scrolls

    const sun = new THREE.DirectionalLight('#fff4e2', 2.8);
    sun.position.set(4, 9, 6);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    Object.assign(sun.shadow.camera, { left: -6, right: 6, top: 4, bottom: -4, near: 1, far: 30 });
    sun.shadow.bias = -0.0006;
    sun.shadow.normalBias = 0.02;
    scene.add(sun);
    scene.add(new THREE.HemisphereLight('#bcd2ff', '#1b2b55', 0.6));

    const ship = new THREE.Group();
    ship.name = 'BRP Gabriela Silang';
    scene.add(ship);
    const mat = (color, extra = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.45, metalness: 0.05, ...extra });
    const add = (geometry, material, parent = ship) => {
        const m = new THREE.Mesh(geometry, material);
        m.castShadow = true; m.receiveShadow = true;
        parent.add(m); return m;
    };
    const box = (x0, x1, y0, y1, width, material, parent = ship) => {
        const m = add(new THREE.BoxGeometry(x1 - x0, y1 - y0, width), material, parent);
        m.position.set((x0 + x1) / 2, (y0 + y1) / 2, 0);
        return m;
    };
    // side profile (x, y) extruded across the ship and centred
    const profileBlock = (points, width, material, parent = ship) => {
        const s = new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x, y)));
        const g = new THREE.ExtrudeGeometry(s, { depth: width, bevelEnabled: false });
        g.translate(0, 0, -width / 2);
        return add(g, material, parent);
    };

    // ------------------------------------------------------------ hull (lofted cross-sections)
    const X0 = -4.2, X1 = 4.35;
    const deckY = (x) => {
        if (x < -0.76) return 0.52;                                   // aft working deck / helideck level
        if (x < -0.74) return 0.52 + (x + 0.76) / 0.02 * 0.34;        // step up to the forecastle deck
        if (x < 2.9) return 0.86 + (x + 0.74) / 3.64 * 0.06;
        return 0.92 + Math.pow((x - 2.9) / 1.45, 2) * 0.16;           // sheer rises to the bow
    };
    const keelY = (x) => {
        if (x < -3.6) return -0.32 + (-3.6 - x) / 0.6 * 0.14;          // run up to the transom
        if (x < 3.0) return -0.32;
        return -0.32 + Math.pow((x - 3.0) / 1.35, 1.7) * 1.4;         // raked stem meets the deck at the bow
    };
    const halfBeam = (x) => {
        if (x < -3.4) return 0.64 + (x + 3.4) / -0.8 * -0.03;
        if (x < 1.4) return 0.66;
        return Math.max(0, 0.66 * (1 - Math.pow((x - 1.4) / (X1 - 1.4), 2.1)));
    };
    const STATIONS = 140;
    const ringCount = 11; // starboard deck edge → keel → port deck edge
    const hullPos = [], hullUv = [], hullIdx = [];
    const uvOf = (x, y) => [(x - X0) / (X1 - X0), (y + 0.35) / 1.5];
    for (let i = 0; i <= STATIONS; i++) {
        const x = X0 + (X1 - X0) * i / STATIONS;
        const top = deckY(x), bot = Math.min(keelY(x), top), w = halfBeam(x);
        const ylerp = (f) => bot + (top - bot) * f;
        // starboard half: [z, y]
        const half = [
            [w, top], [w * 0.995, ylerp(0.7)], [w * 0.97, ylerp(0.5)],
            [w * 0.86, ylerp(0.28)], [w * 0.55, ylerp(0.1)], [0, bot],
        ];
        const ring = [...half, ...half.slice(0, -1).reverse().map(([z, y]) => [-z, y])];
        ring.forEach(([z, y]) => { hullPos.push(x, y, z); hullUv.push(...uvOf(x, y)); });
    }
    for (let i = 0; i < STATIONS; i++) {
        for (let k = 0; k < ringCount - 1; k++) {
            const a = i * ringCount + k, b = a + 1, c = a + ringCount, d = c + 1;
            hullIdx.push(a, c, b, b, c, d);
        }
    }
    // deck (strip between the two deck edges) and transom (fan at the stern)
    const deckStart = hullPos.length / 3;
    for (let i = 0; i <= STATIONS; i++) {
        const x = X0 + (X1 - X0) * i / STATIONS, y = deckY(x) + 0.002, w = halfBeam(x);
        hullPos.push(x, y, w, x, y, -w);
        hullUv.push(...uvOf(x, 1.3), ...uvOf(x, 1.3)); // deck samples the plain part of the texture
    }
    for (let i = 0; i < STATIONS; i++) {
        const a = deckStart + i * 2;
        hullIdx.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
    }
    for (let k = 1; k < ringCount - 1; k++) hullIdx.push(0, k + 1, k);
    const hullGeo = new THREE.BufferGeometry();
    hullGeo.setAttribute('position', new THREE.Float32BufferAttribute(hullPos, 3));
    hullGeo.setAttribute('uv', new THREE.Float32BufferAttribute(hullUv, 2));
    hullGeo.setIndex(hullIdx);
    hullGeo.computeVertexNormals();

    // hull paint: white, dark boot-top at the waterline, slanted blue and red bow stripes
    function hullTexture() {
        const W = 2048, H = 360;
        const c = document.createElement('canvas'); c.width = W; c.height = H;
        const g = c.getContext('2d');
        const px = (x, y) => { const [u, v] = uvOf(x, y); return [u * W, (1 - v) * H]; };
        g.fillStyle = C.hull; g.fillRect(0, 0, W, H);
        const [, yBoot] = px(0, 0.1);
        g.fillStyle = C.boot; g.fillRect(0, yBoot, W, H - yBoot);
        const poly = (pts, color) => {
            g.beginPath(); pts.forEach(([x, y], i) => { const [a, b] = px(x, y); i ? g.lineTo(a, b) : g.moveTo(a, b); });
            g.closePath(); g.fillStyle = color; g.fill();
        };
        poly([[1.28, 0.1], [1.41, 0.1], [2.25, 1.2], [2.12, 1.2]], C.stripeBlue);
        poly([[1.5, 0.1], [1.8, 0.1], [2.64, 1.2], [2.34, 1.2]], C.stripeRed);
        // dark accent lines along the aft hull, as on the ship
        g.strokeStyle = C.boot; g.lineWidth = 5;
        [[-2.9, 0.34, -1.2, 0.34], [-0.9, 0.62, 1.0, 0.62]].forEach(([a, b, c2, d]) => {
            const [x1, y1] = px(a, b), [x2, y2] = px(c2, d); g.beginPath(); g.moveTo(x1, y1); g.lineTo(x2, y2); g.stroke();
        });
        const t = new THREE.CanvasTexture(c);
        t.colorSpace = THREE.SRGBColorSpace;
        t.anisotropy = renderer.capabilities.getMaxAnisotropy();
        return t;
    }
    add(hullGeo, mat('#ffffff', { map: hullTexture(), roughness: 0.38, side: THREE.DoubleSide }));

    // painted words on both sides (separate decals so they never read mirrored)
    function decal(text, { font, color, width, height, x, y, z }) {
        const cw = 1024, ch = Math.round(cw * height / width);
        const c = document.createElement('canvas'); c.width = cw; c.height = ch;
        const g = c.getContext('2d');
        g.font = font.replace('{s}', String(Math.round(ch * 0.78)));
        g.fillStyle = color; g.textAlign = 'center'; g.textBaseline = 'middle';
        // spread letters across the width
        const chars = [...text], widths = chars.map((k) => g.measureText(k).width);
        const sum = widths.reduce((a, b) => a + b, 0), gap = Math.max(0, (cw * 0.98 - sum) / Math.max(1, chars.length - 1));
        let cx = (cw - (sum + gap * (chars.length - 1))) / 2;
        chars.forEach((k, i) => { g.fillText(k, cx + widths[i] / 2, ch / 2); cx += widths[i] + gap; });
        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
        const m = new THREE.MeshStandardMaterial({ map: tex, transparent: true, roughness: 0.4, polygonOffset: true, polygonOffsetFactor: -2 });
        // follow the hull's taper so the decal lies flat on the side (matters toward the bow)
        const slope = (halfBeam(x + 0.01) - halfBeam(x - 0.01)) / 0.02;
        [1, -1].forEach((side) => {
            const p = new THREE.Mesh(new THREE.PlaneGeometry(width, height), m);
            p.position.set(x, y, side * z);
            p.rotation.y = side > 0 ? -Math.atan(slope) : Math.PI + Math.atan(slope);
            ship.add(p);
        });
    }
    decal('PHILIPPINE COAST GUARD', { font: '700 {s}px Inter, Arial, sans-serif', color: C.text, width: 1.9, height: 0.17, x: -0.3, y: 0.44, z: 0.664 });
    decal('8301', { font: '800 {s}px Inter, Arial, sans-serif', color: C.number, width: 0.5, height: 0.19, x: 3.15, y: 0.6, z: halfBeam(3.15) + 0.014 });

    // ------------------------------------------------------------ superstructure
    const white = mat(C.hull, { roughness: 0.4 });
    const blue = mat(C.blueDeck, { roughness: 0.6 });
    const dark = mat(C.bay, { roughness: 0.7 });
    const glass = mat(C.glass, { roughness: 0.12, metalness: 0.6 });
    const W1 = 1.2;
    // aft part beside the boat bay, the roof over the bay, and the forward block with its raked front
    profileBlock([[-1.26, 0.52], [-0.63, 0.52], [-0.63, 1.45], [-1.26, 1.45]], W1, white);
    profileBlock([[-0.63, 1.22], [0.39, 1.22], [0.39, 1.45], [-0.63, 1.45]], W1, white);
    profileBlock([[0.39, 0.86], [2.1, 0.86], [1.86, 1.45], [0.39, 1.45]], W1, white);
    // boat bay: a dark tunnel through the ship with the rescue boat inside
    box(-0.63, 0.39, 0.86, 1.22, 0.7, dark);
    box(-0.63, 0.39, 0.52, 0.86, 0.7, dark);
    const rhib = new THREE.Group(); ship.add(rhib);
    const tube = add(new THREE.CapsuleGeometry(0.06, 0.55, 6, 16), mat(C.rhib, { roughness: 0.7 }), rhib);
    tube.rotation.z = Math.PI / 2; tube.scale.set(1, 1, 2.2);
    box(-0.3, 0.3, 0.06, 0.14, 0.18, mat('#2f353d'), rhib).position.y = 0.11;
    rhib.position.set(-0.12, 0.98, 0.42);
    // dark side panels so the bay reads as an opening from both sides
    [1, -1].forEach((s) => {
        const p = new THREE.Mesh(new THREE.PlaneGeometry(1.02, 0.7), mat('#2a3038', { roughness: 0.9 }));
        p.position.set(-0.12, 0.87, s * 0.351); if (s < 0) p.rotation.y = Math.PI; ship.add(p);
    });
    // roof decks painted blue
    box(-1.24, -0.86, 1.45, 1.47, W1 - 0.04, blue);
    box(1.0, 1.84, 1.45, 1.47, W1 - 0.1, blue);
    // bridge with its window band
    profileBlock([[-0.86, 1.45], [1.0, 1.45], [0.9, 1.85], [-0.8, 1.85]], 1.0, white);
    profileBlock([[-0.83, 1.6], [0.965, 1.6], [0.93, 1.75], [-0.81, 1.75]], 1.02, glass);
    box(-0.82, 0.92, 1.85, 1.87, 0.98, blue);
    // forecastle deck painted blue, with a low bulwark
    const fore = add(new THREE.BoxGeometry(0.95, 0.02, 0.9), blue);
    fore.position.set(2.55, deckY(2.55) + 0.02, 0);
    // helideck with its marking, and the aft working deck
    function helideckTexture() {
        const c = document.createElement('canvas'); c.width = 512; c.height = 256;
        const g = c.getContext('2d');
        g.fillStyle = '#c9ced6'; g.fillRect(0, 0, 512, 256);
        g.strokeStyle = '#f7f8fa'; g.lineWidth = 9;
        g.beginPath(); g.arc(256, 128, 82, 0, Math.PI * 2); g.stroke();
        g.lineWidth = 6; g.strokeRect(14, 14, 484, 228);
        const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
    }
    const heli = add(new THREE.BoxGeometry(2.05, 0.06, 1.24), [mat('#c9ced6'), mat('#c9ced6'), mat('#ffffff', { map: helideckTexture(), roughness: 0.75 }), mat('#c9ced6'), mat('#c9ced6'), mat('#c9ced6')]);
    heli.position.set(-2.3, 0.6, 0);
    box(-4.18, -3.32, 0.52, 0.54, 1.22, blue);
    // equipment on the aft deck: crane, life rafts, a boat
    box(-3.8, -3.55, 0.54, 0.74, 0.3, mat('#d38b2c', { roughness: 0.6 })).position.z = 0.25;
    [-0.42, -0.28].forEach((z) => {
        const raft = add(new THREE.CylinderGeometry(0.06, 0.06, 0.2, 16), white);
        raft.rotation.z = Math.PI / 2; raft.position.set(-3.95, 0.62, z);
    });
    // railings: thin rails along the helideck, the roof decks and the bow
    const rail = mat('#e9edf1', { roughness: 0.35, metalness: 0.4 });
    const railRun = (x0, x1, y, z) => [1, -1].forEach((s) => {
        const r = add(new THREE.BoxGeometry(x1 - x0, 0.012, 0.012), rail);
        r.position.set((x0 + x1) / 2, y, s * z);
        for (let x = x0; x <= x1 + 1e-6; x += 0.18) {
            const post = add(new THREE.BoxGeometry(0.01, 0.1, 0.01), rail);
            post.position.set(x, y - 0.05, s * z);
        }
    });
    railRun(-3.3, -1.3, 0.73, 0.6);
    railRun(-4.15, -3.35, 0.65, 0.6);
    railRun(1.0, 1.84, 1.57, 0.57);
    railRun(2.1, 3.0, deckY(2.5) + 0.12, 0.47);

    // ------------------------------------------------------------ mast, radars, flag
    const mastMat = mat(C.mast, { roughness: 0.4 });
    const mast = add(new THREE.CylinderGeometry(0.05, 0.08, 1.1, 4), mastMat);
    mast.rotation.y = Math.PI / 4; mast.position.set(-0.24, 2.4, 0);
    [[2.42, 0.62], [2.72, 0.42]].forEach(([y, w]) => {
        const yard = add(new THREE.BoxGeometry(0.04, 0.04, w), mastMat);
        yard.position.set(-0.24, y, 0);
    });
    const dome = add(new THREE.SphereGeometry(0.07, 20, 14), mastMat); dome.position.set(-0.24, 3.02, 0);
    const radar = add(new THREE.BoxGeometry(0.06, 0.03, 0.42), mastMat); radar.position.set(-0.24, 2.84, 0);
    [[0.55, 1.95, 0.32], [0.25, 1.95, -0.3]].forEach(([x, y, z]) => {
        const d = add(new THREE.SphereGeometry(0.06, 16, 12), mastMat); d.position.set(x, y, z);
    });
    // gaff angled aft, flag at its end
    const gaff = add(new THREE.CylinderGeometry(0.012, 0.012, 0.75, 8), mastMat);
    gaff.position.set(-0.55, 2.55, 0); gaff.rotation.z = Math.PI / 2 - 0.35;
    function flagTexture() {
        const c = document.createElement('canvas'); c.width = 512; c.height = 256;
        const g = c.getContext('2d');
        g.fillStyle = '#0038a8'; g.fillRect(0, 0, 512, 128);
        g.fillStyle = '#ce1126'; g.fillRect(0, 128, 512, 128);
        g.fillStyle = '#ffffff'; g.beginPath(); g.moveTo(0, 0); g.lineTo(222, 128); g.lineTo(0, 256); g.closePath(); g.fill();
        g.fillStyle = '#fcd116';
        g.beginPath(); g.arc(74, 128, 24, 0, Math.PI * 2); g.fill();
        for (let i = 0; i < 8; i++) { // sun rays
            const a = i * Math.PI / 4; g.save(); g.translate(74, 128); g.rotate(a);
            g.beginPath(); g.moveTo(26, -5); g.lineTo(46, 0); g.lineTo(26, 5); g.closePath(); g.fill(); g.restore();
        }
        [[18, 26], [18, 230], [190, 128]].forEach(([x, y]) => { g.beginPath(); g.arc(x, y, 9, 0, Math.PI * 2); g.fill(); });
        const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
    }
    const flagGeo = new THREE.PlaneGeometry(0.56, 0.28, 24, 6);
    flagGeo.translate(-0.28, 0, 0); // hoist at the origin, fly toward -x (aft)
    const flagBase = flagGeo.attributes.position.array.slice();
    const flag = new THREE.Mesh(flagGeo, new THREE.MeshStandardMaterial({ map: flagTexture(), side: THREE.DoubleSide, roughness: 0.8 }));
    flag.castShadow = true;
    flag.position.set(-0.86, 2.53, 0);
    ship.add(flag);

    // ------------------------------------------------------------ sea and wake
    function radialAlpha() {
        const c = document.createElement('canvas'); c.width = c.height = 256;
        const g = c.getContext('2d');
        const gr = g.createRadialGradient(128, 128, 40, 128, 128, 128);
        gr.addColorStop(0, '#ffffff'); gr.addColorStop(0.7, '#ffffff'); gr.addColorStop(1, '#000000');
        g.fillStyle = gr; g.fillRect(0, 0, 256, 256);
        return new THREE.CanvasTexture(c);
    }
    const SEA_SEG = 110;
    const seaGeo = new THREE.PlaneGeometry(26, 26, SEA_SEG, SEA_SEG);
    seaGeo.rotateX(-Math.PI / 2);
    const seaBase = seaGeo.attributes.position.array.slice();
    const sea = new THREE.Mesh(seaGeo, new THREE.MeshStandardMaterial({
        color: C.sea, roughness: 0.32, metalness: 0.15, transparent: true, alphaMap: radialAlpha(), depthWrite: false,
    }));
    sea.receiveShadow = true;
    scene.add(sea);
    const waveY = (x, z, t) => 0.035 * Math.sin(x * 1.6 + t * 1.3) + 0.025 * Math.sin(z * 2.3 - t * 1.1) + 0.015 * Math.sin((x + z) * 3.7 + t * 2.0);

    function foamTexture() {
        const c = document.createElement('canvas'); c.width = 512; c.height = 128;
        const g = c.getContext('2d');
        for (let i = 0; i < 1400; i++) {
            const x = Math.random() * 512, y = 64 + (Math.random() - 0.5) * (20 + x * 0.18);
            const a = Math.max(0, 0.9 - x / 600) * Math.random();
            g.fillStyle = `rgba(255,255,255,${a.toFixed(3)})`;
            g.beginPath(); g.arc(x, y, 1 + Math.random() * 2.2, 0, Math.PI * 2); g.fill();
        }
        return new THREE.CanvasTexture(c);
    }
    const foam = new THREE.MeshBasicMaterial({ map: foamTexture(), transparent: true, depthWrite: false, opacity: 0.9 });
    const wake = new THREE.Mesh(new THREE.PlaneGeometry(6, 1.6), foam);
    wake.rotation.x = -Math.PI / 2; wake.rotation.z = Math.PI;
    wake.position.set(-7.2, 0.06, 0);
    scene.add(wake);
    [1, -1].forEach((s) => { // bow waves running down both sides
        const w = new THREE.Mesh(new THREE.PlaneGeometry(3.4, 0.5), foam);
        w.rotation.x = -Math.PI / 2; w.rotation.z = Math.PI + s * 0.12;
        w.position.set(2.2, 0.07, s * 0.75);
        scene.add(w);
    });

    // ------------------------------------------------------------ size, visibility, loop
    const resize = () => {
        const w = stage.clientWidth, h = stage.clientHeight || Math.round(w * 0.62);
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };
    resize();
    if ('ResizeObserver' in window) new ResizeObserver(resize).observe(stage);
    else window.addEventListener('resize', resize);

    let onScreen = true;
    new IntersectionObserver((entries) => { onScreen = entries.some((e) => e.isIntersecting); }).observe(stage);

    const seaPos = seaGeo.attributes.position;
    const flagPos = flagGeo.attributes.position;
    function animate(t) {
        for (let i = 0; i < seaPos.count; i++) {
            const x = seaBase[i * 3], z = seaBase[i * 3 + 2];
            seaPos.setY(i, waveY(x, z, t));
        }
        seaPos.needsUpdate = true;
        seaGeo.computeVertexNormals();
        for (let i = 0; i < flagPos.count; i++) { // flag ripples from the hoist outward
            const x = flagBase[i * 3], y = flagBase[i * 3 + 1];
            const k = -x / 0.56;
            flagPos.setZ(i, Math.sin(x * 14 + t * 7) * 0.035 * k + Math.sin(y * 9 + t * 5) * 0.01 * k);
        }
        flagPos.needsUpdate = true;
        flagGeo.computeVertexNormals();
        ship.position.y = Math.sin(t * 0.9) * 0.025;
        ship.rotation.x = Math.sin(t * 0.7) * 0.018;   // roll
        ship.rotation.z = Math.sin(t * 0.55) * 0.008;  // pitch
        foam.map.offset.x = (t * 0.06) % 1;
    }

    // first frame now, then swap the photo for the canvas (it fades in)
    animate(0);
    controls.update();
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
