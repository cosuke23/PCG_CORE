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
 * - The ship geometry is a GLB built in Blender (tools/blender/brp_gabriela_silang.py,
 *   public/models/brp-gabriela-silang.glb); paint, lettering, seal and flag are added here.
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

    // ------------------------------------------------------------ the ship model (built in Blender)
    // tools/blender/brp_gabriela_silang.py builds the geometry in metres and exports
    // public/models/brp-gabriela-silang.glb; this scene scales it to 1 unit = 10 m and gives it
    // the materials, paint, lettering, seal and flag. The hull shape functions below match the
    // script and place the painted decals on the hull sides.
    const X0 = -4.2, X1 = 4.22, DECK = 0.7;
    const halfBeam = (x) => {
        if (x < -3.4) return 0.8 - Math.pow((-3.4 - x) / 0.8, 2) * 0.04;
        if (x < 0.5) return 0.8;
        return Math.max(0.004, 0.8 * (1 - Math.pow((x - 0.5) / (X1 - 0.5), 2.0)));
    };
    const uvOf = (x, y) => [(x - X0) / (X1 - X0), (y + 0.4) / 1.4];

    // hull paint, positions measured from the side photos (glTF UVs: flipY off)
    const hullTex = canvasTex(4096, 700, (g, W, H) => {
        const px = (x, y) => { const [u, v] = uvOf(x, y); return [u * W, (1 - v) * H]; };
        const poly = (pts, color) => {
            g.beginPath(); pts.forEach(([x, y], i) => { const [a, b] = px(x, y); i ? g.lineTo(a, b) : g.moveTo(a, b); });
            g.closePath(); g.fillStyle = color; g.fill();
        };
        const line = (a, b, c2, d, wpx, color = C.boot) => { const [x1, y1] = px(a, b), [x2, y2] = px(c2, d); g.strokeStyle = color; g.lineWidth = wpx; g.beginPath(); g.moveTo(x1, y1); g.lineTo(x2, y2); g.stroke(); };
        g.fillStyle = C.hull; g.fillRect(0, 0, W, H);
        poly([[-4.4, -0.5], [-4.4, 0.21], [-2.45, 0.21], [-2.35, 0.05], [4.4, 0.05], [4.4, -0.5]], C.boot);   // dark bottom, deep band at the stern
        [-2.3, -2.0, -1.7].forEach((x) => line(x, 0.05, x + 0.26, 0.31, 10));                          // slanted aft accents
        line(-2.42, 0.31, -0.62, 0.31, 7); line(0.02, 0.28, 0.95, 0.28, 7); line(0.2, 0.56, 1.28, 0.56, 6);    // black lines
        [-0.62, -0.18].forEach((x) => line(x, 0.1, x, 0.66, 4, '#9aa3ad'));                              // shell door seams
        poly([[1.13, 0.05], [1.25, 0.05], [1.95, 1.0], [1.83, 1.0]], C.stripeBlue);                      // bow stripes
        poly([[1.33, 0.05], [1.6, 0.05], [2.34, 1.0], [2.07, 1.0]], C.stripeRed);
        poly([[3.13, 0.44], [3.33, 0.44], [3.33, 0.72], [3.13, 0.72]], '#1c232b');                       // anchor recess
        poly([[3.16, 0.47], [3.3, 0.47], [3.3, 0.69], [3.16, 0.69]], '#3a4450');
        g.fillStyle = '#2a3340';                                                                          // portholes and small windows
        [2.84, 2.95, 3.3, 3.41].forEach((x) => { const [a, b] = px(x, 0.8); g.beginPath(); g.arc(a, b, 7, 0, Math.PI * 2); g.fill(); });
        [[-2.95, 0.6], [-2.84, 0.6], [-0.95, 0.6], [-0.84, 0.6]].forEach(([x, y]) => { const [a, b] = px(x, y); g.fillRect(a - 9, b - 6, 18, 12); });
    });
    hullTex.flipY = false;
    const heliTex = canvasTex(1024, 512, (g, w, h) => {
        g.fillStyle = '#c3c9d1'; g.fillRect(0, 0, w, h);
        g.strokeStyle = '#f7f8fa'; g.lineWidth = 16; g.beginPath(); g.arc(w / 2, h / 2, 170, 0, Math.PI * 2); g.stroke();
        g.lineWidth = 10; g.strokeRect(24, 24, w - 48, h - 48);
        g.strokeStyle = '#f2c94c'; g.lineWidth = 8; g.beginPath(); g.arc(w / 2, h / 2, 118, 0, Math.PI * 2); g.stroke();
    });
    heliTex.flipY = false;
    const mullionTex = canvasTex(512, 64, (g, w, h) => {
        g.fillStyle = C.glass; g.fillRect(0, 0, w, h);
        g.fillStyle = '#c9d1db'; for (let x = 0; x < w; x += 44) g.fillRect(x, 0, 4, h);
        const grd = g.createLinearGradient(0, 0, 0, h); grd.addColorStop(0, 'rgba(255,255,255,0.2)'); grd.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = grd; g.fillRect(0, 0, w, h);
    });
    mullionTex.wrapS = mullionTex.wrapT = THREE.RepeatWrapping;
    mullionTex.flipY = false;

    const MATERIALS = {
        HullPaint: paint('#ffffff', { map: hullTex }),
        White: paint(C.hull),
        Mast: paint(C.mast),
        Radar: paint('#f6f7f9'),
        BlueDeck: mat(C.blueDeck, { roughness: 0.7 }),
        Dark: mat(C.bay, { roughness: 0.75 }),
        Glass: mat(C.glass, { roughness: 0.08, metalness: 0.7, envMapIntensity: 1.4 }),
        BridgeGlass: mat('#ffffff', { map: mullionTex, roughness: 0.08, metalness: 0.6, envMapIntensity: 1.4 }),
        Rail: mat('#e6eaee', { roughness: 0.35, metalness: 0.5 }),
        Steel: mat('#9aa3ad', { roughness: 0.4, metalness: 0.6 }),
        Orange: mat(C.orange, { roughness: 0.6 }),
        RhibGrey: mat(C.rhib, { roughness: 0.75 }),
        Rubber: mat('#2c3238', { roughness: 0.8 }),
        Helideck: mat('#ffffff', { map: heliTex, roughness: 0.8 }),
        HangarDoor: mat('#aeb6c0', { roughness: 0.55, metalness: 0.3 }),
    };
    const deckGrey = mat(C.deck, { roughness: 0.85 });

    const { GLTFLoader } = await import('three/addons/loaders/GLTFLoader.js');
    const gltf = await new GLTFLoader().loadAsync(new URL('../models/brp-gabriela-silang.glb', import.meta.url).href);
    const model = gltf.scene;
    model.scale.setScalar(0.1);                       // metres -> scene units (1 = 10 m)
    model.traverse((o) => {
        if (!o.isMesh) return;
        o.castShadow = true; o.receiveShadow = true;
        if (o.name === 'MainDeck' || (o.parent && o.parent.name === 'MainDeck')) { o.material = deckGrey; return; }
        const pick = (m) => MATERIALS[m.name] || m;
        o.material = Array.isArray(o.material) ? o.material.map(pick) : pick(o.material);
    });
    ship.add(model);
    const sBand = model.getObjectByName('RadarS') || new THREE.Object3D();
    const xBand = model.getObjectByName('RadarX') || new THREE.Object3D();

    // painted words and emblems on both sides (separate decals laid along the hull, never mirrored)
    function sideDecal(tex, { width, height, x, y, z = null }) {
        const m = new THREE.MeshStandardMaterial({ map: tex, transparent: true, roughness: 0.4, polygonOffset: true, polygonOffsetFactor: -2 });
        const onHull = z === null;
        const slope = onHull ? (halfBeam(x + 0.01) - halfBeam(x - 0.01)) / 0.02 : 0;
        const zz = onHull ? halfBeam(x) + 0.006 : z;
        [1, -1].forEach((side) => {
            const p = new THREE.Mesh(new THREE.PlaneGeometry(width, height), m);
            p.position.set(x, y, side * zz);
            p.rotation.y = side > 0 ? -Math.atan(slope) : Math.PI + Math.atan(slope);
            ship.add(p);
        });
    }
    const textTex = (text, font, color, aspect, plate = null) => canvasTex(1024, Math.round(1024 / aspect), (g, cw, ch) => {
        if (plate) { g.fillStyle = plate; g.fillRect(0, 0, cw, ch); }
        g.font = font.replace('{s}', String(Math.round(ch * 0.78)));
        g.fillStyle = color; g.textAlign = 'center'; g.textBaseline = 'middle';
        const chars = [...text], widths = chars.map((k) => g.measureText(k).width);
        const sum = widths.reduce((a, b) => a + b, 0), gap = Math.max(0, (cw * 0.96 - sum) / Math.max(1, chars.length - 1));
        let cx = (cw - (sum + gap * (chars.length - 1))) / 2;
        chars.forEach((k, i) => { g.fillText(k, cx + widths[i] / 2, ch / 2 + ch * 0.03); cx += widths[i] + gap; });
    });
    sideDecal(textTex('PHILIPPINE COAST GUARD', '700 {s}px Inter, Arial, sans-serif', C.text, 1.72 / 0.13), { width: 1.72, height: 0.13, x: -0.57, y: 0.43 });
    sideDecal(textTex('8301', '800 {s}px Inter, Arial, sans-serif', C.number, 0.44 / 0.15), { width: 0.44, height: 0.15, x: 2.57, y: 0.55 });
    const seal = new THREE.TextureLoader().load(new URL('../images/brand/pcg-logo.webp', import.meta.url).href);
    seal.colorSpace = THREE.SRGBColorSpace;
    sideDecal(seal, { width: 0.2, height: 0.2, x: 0.34, y: 0.9, z: 0.762 });          // PCG seal beside the boat bay
    sideDecal(seal, { width: 0.07, height: 0.07, x: 1.86, y: 0.5 });                  // small seal on the bow stripe
    sideDecal(textTex('GABRIELA SILANG', '700 {s}px Inter, Arial, sans-serif', '#1b1f25', 10, '#f7f8f9'), { width: 0.5, height: 0.05, x: -0.45, y: 1.06, z: 0.764 });
    const sternName = new THREE.Mesh(new THREE.PlaneGeometry(0.6, 0.06), new THREE.MeshStandardMaterial({ map: textTex('GABRIELA SILANG', '700 {s}px Inter, Arial, sans-serif', '#1b1f25', 10), transparent: true, polygonOffset: true, polygonOffsetFactor: -2 }));
    sternName.position.set(X0 - 0.004, 0.42, 0); sternName.rotation.y = -Math.PI / 2; ship.add(sternName);

    // ------------------------------------------------------------ Philippine flag on the gaff (cloth)
    const flagTex = canvasTex(512, 256, (g) => {
        g.fillStyle = '#0038a8'; g.fillRect(0, 0, 512, 128);
        g.fillStyle = '#ce1126'; g.fillRect(0, 128, 512, 128);
        g.fillStyle = '#ffffff'; g.beginPath(); g.moveTo(0, 0); g.lineTo(222, 128); g.lineTo(0, 256); g.closePath(); g.fill();
        g.fillStyle = '#fcd116'; g.beginPath(); g.arc(74, 128, 24, 0, Math.PI * 2); g.fill();
        for (let i = 0; i < 8; i++) { g.save(); g.translate(74, 128); g.rotate(i * Math.PI / 4); g.beginPath(); g.moveTo(26, -5); g.lineTo(46, 0); g.lineTo(26, 5); g.closePath(); g.fill(); g.restore(); }
        [[18, 26], [18, 230], [190, 128]].forEach(([x, y]) => { g.beginPath(); g.arc(x, y, 9, 0, Math.PI * 2); g.fill(); });
    });
    // hoist (the white triangle with the sun and stars) at the gaff, x = 0; the fly streams aft (-x)
    const flagGeo = new THREE.PlaneGeometry(0.5, 0.25, 24, 6);
    flagGeo.translate(-0.25, 0, 0);
    {
        const uv = flagGeo.attributes.uv;
        for (let i = 0; i < uv.count; i++) uv.setX(i, 1 - uv.getX(i)); // texture left edge (hoist) at x = 0
        uv.needsUpdate = true;
    }
    const flagBase = flagGeo.attributes.position.array.slice();
    const flag = new THREE.Mesh(flagGeo, new THREE.MeshStandardMaterial({ map: flagTex, side: THREE.DoubleSide, roughness: 0.85 }));
    flag.castShadow = true; flag.position.set(-0.83, 1.99, 0); ship.add(flag);  // gaff tip in the model: x -8.3 m, z 21.2 m

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
    const target = new THREE.Vector3(0.0, 0.62, 0);
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
            const x = flagBase[i * 3], y = flagBase[i * 3 + 1], k = -x / 0.5;
            flagPos.setZ(i, Math.sin(x * 15 + t * 7) * 0.032 * k + Math.sin(y * 9 + t * 5) * 0.01 * k);
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
