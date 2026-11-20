/*
 * The Philippine Coast Guard seal in 3D on the PCG HRMIS landing page
 * (resources/views/pages/pcg.blade.php, section #seal). Built with three.js
 * (self-hosted: public/vendor/three-0.160.0, mapped by the page's import map).
 *
 * - Nothing is loaded until the section comes near the screen.
 * - The seal image stays in place until the 3D seal has drawn its first frame;
 *   without WebGL, or if anything fails, the image simply stays.
 * - Rendering pauses while the section is off screen or the tab is hidden.
 * - "Reduce motion": no idle sway; the seal still turns when dragged.
 */
const stage = document.querySelector('[data-pcg-seal-3d]');

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
            start().catch((err) => { console.warn('PCG seal 3D not shown; keeping the image.', err); });
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

    // Colours sampled from the seal image.
    const PALETTE = { orange: '#ea6006', white: '#f7f7f4', black: '#111111', navy: '#01026f', gold: '#eec632', dolphin: '#8eb7d2' };
    const RING = { radius: 0.81, tube: 0.155, depth: 0.62 };

    // ------------------------------------------------------------ renderer, scene, camera
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.setClearColor(0x000000, 0);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    const canvas = renderer.domElement;
    canvas.className = 'ph-seal__canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Seal of the Philippine Coast Guard in 3D. Drag to turn it.');

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.75;

    const camera = new THREE.PerspectiveCamera(30, 1, 0.05, 50);
    camera.position.set(0, 0.1, 4.3);

    const controls = new OrbitControls(camera, canvas);
    controls.enableDamping = true;
    controls.enableZoom = false;   // the page scroll stays the page scroll
    controls.enablePan = false;
    controls.rotateSpeed = 0.7;
    // phones: a sideways drag turns the seal, an up/down swipe still scrolls the page
    canvas.style.touchAction = 'pan-y';

    const key = new THREE.DirectionalLight('#fff6ea', 2.6);
    key.position.set(-2.5, 3.2, 4);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    Object.assign(key.shadow.camera, { left: -1.4, right: 1.4, top: 1.4, bottom: -1.4, near: 0.5, far: 12 });
    key.shadow.bias = -0.0004;
    key.shadow.normalBias = 0.01;
    scene.add(key);
    const rim = new THREE.DirectionalLight('#9fb8ff', 1.1);
    rim.position.set(3, 1, -3);
    scene.add(rim);

    // ------------------------------------------------------------ helpers
    const seal = new THREE.Group();
    scene.add(seal);
    const enamel = (color, extra = {}) => new THREE.MeshPhysicalMaterial({ color, roughness: 0.32, metalness: 0, clearcoat: 0.6, clearcoatRoughness: 0.18, ...extra });
    const metal = (color, extra = {}) => new THREE.MeshStandardMaterial({ color, roughness: 0.28, metalness: 1, ...extra });
    const mesh = (geometry, material, parent = seal) => {
        const m = new THREE.Mesh(geometry, material);
        m.castShadow = true; m.receiveShadow = true;
        parent.add(m); return m;
    };
    const extrude = (shape, depth, bevel = 0.006) => new THREE.ExtrudeGeometry(shape, {
        depth, bevelEnabled: bevel > 0, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 3, curveSegments: 24,
    });
    const starShape = (r, inner = 0.42) => {
        const s = new THREE.Shape();
        for (let i = 0; i < 10; i++) {
            const a = Math.PI / 2 + i * Math.PI / 5;
            const rr = i % 2 ? r * inner : r;
            i ? s.lineTo(Math.cos(a) * rr, Math.sin(a) * rr) : s.moveTo(Math.cos(a) * rr, Math.sin(a) * rr);
        }
        s.closePath();
        return s;
    };

    // ------------------------------------------------------------ white enamel disc
    const discR = RING.radius - RING.tube * 0.6;
    const disc = mesh(new THREE.CylinderGeometry(discR, discR, 0.07, 128), enamel(PALETTE.white));
    disc.rotation.x = Math.PI / 2;
    disc.position.z = -0.01;
    const innerLine = mesh(new THREE.TorusGeometry(0.615, 0.006, 8, 160), enamel(PALETTE.black, { roughness: 0.5 }));
    innerLine.position.z = 0.026;

    // ------------------------------------------------------------ life ring with lettering
    // Torus UVs: u around the ring (0 = 3 o'clock, counter-clockwise), v around the
    // tube (0.25 = the face toward the viewer). Canvas aspect = ring : tube radius.
    function ringTexture() {
        const W = 2048, H = Math.round(W * RING.tube / RING.radius / 4) * 4;
        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const g = c.getContext('2d');
        g.fillStyle = PALETTE.orange;
        g.fillRect(0, 0, W, H);
        g.fillStyle = PALETTE.black;
        g.fillRect(0, H - H * 0.035, W, H * 0.035);
        g.fillRect(0, 0, W, H * 0.02);
        g.fillRect(0, H * 0.48, W, H * 0.04);
        const yFront = H * 0.75;
        const word = (text, xCenter, upsideDown, size, span) => {
            g.save();
            g.translate(xCenter, yFront);
            if (upsideDown) g.rotate(Math.PI);
            g.font = `800 ${size}px Inter, "Arial Black", Arial, sans-serif`;
            g.textAlign = 'center'; g.textBaseline = 'middle';
            g.fillStyle = PALETTE.black;
            const chars = [...text];
            const widths = chars.map((ch) => g.measureText(ch).width);
            const sum = widths.reduce((a, b) => a + b, 0);
            const spacing = Math.max(0, (span - sum) / (chars.length - 1));
            let x = -(sum + spacing * (chars.length - 1)) / 2;
            chars.forEach((ch, i) => { g.fillText(ch, x + widths[i] / 2, 0); x += widths[i] + spacing; });
            g.restore();
        };
        const size = H * 0.3;
        word('PHILIPPINE', W * 0.25, true, size, W * 0.31);
        word('COAST', W * 0.625, false, size, W * 0.105);
        word('GUARD', W * 0.875, false, size, W * 0.105);
        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
        tex.wrapS = THREE.RepeatWrapping;
        return tex;
    }
    const ringGeo = new THREE.TorusGeometry(RING.radius, RING.tube, 48, 200);
    ringGeo.scale(1, 1, RING.depth);
    mesh(ringGeo, enamel('#ffffff', { map: ringTexture(), roughness: 0.5, clearcoat: 0.25, clearcoatRoughness: 0.3 }));

    // white straps at 3, 6 and 9 o'clock
    const strapArc = 0.17;
    [0, Math.PI, Math.PI * 1.5].forEach((angle) => {
        const band = new THREE.TorusGeometry(RING.radius, RING.tube * 1.035, 40, 20, strapArc);
        band.scale(1, 1, RING.depth * 1.02);
        band.rotateZ(angle - strapArc / 2);
        mesh(band, enamel(PALETTE.white, { roughness: 0.5, clearcoat: 0.2 }));
        [-1, 1].forEach((side) => {
            const edge = new THREE.TorusGeometry(RING.radius, RING.tube * 1.045, 40, 4, 0.012);
            edge.scale(1, 1, RING.depth * 1.03);
            edge.rotateZ(angle + side * strapArc / 2 - 0.006);
            mesh(edge, enamel(PALETTE.black, { roughness: 0.6 }));
        });
    });

    // ------------------------------------------------------------ navy triangle and stars
    const apex = [0, 0.585], left = [-0.52, -0.325], right = [0.52, -0.325];
    const triShape = (scale) => {
        const s = new THREE.Shape();
        const cy = (apex[1] + left[1] + right[1]) / 3;
        const p = (pt) => [pt[0] * scale, cy + (pt[1] - cy) * scale];
        s.moveTo(...p(apex)); s.lineTo(...p(left)); s.lineTo(...p(right)); s.closePath();
        return s;
    };
    mesh(extrude(triShape(1.0), 0.02, 0.004), enamel(PALETTE.white)).position.z = 0.025;
    mesh(extrude(triShape(0.965), 0.03, 0.006), enamel(PALETTE.navy, { roughness: 0.28, clearcoat: 0.9 })).position.z = 0.03;
    [[0, 0.468], [-0.408, -0.27], [0.408, -0.27]].forEach(([x, y]) => {
        mesh(extrude(starShape(0.032), 0.008, 0.003), enamel('#fff7c8', { roughness: 0.3 })).position.set(x, y, 0.072);
    });

    // ------------------------------------------------------------ gold anchor
    const gold = metal(PALETTE.gold, { roughness: 0.22 });
    const aZ = 0.1;
    mesh(new THREE.CylinderGeometry(0.022, 0.026, 0.56, 24), gold).position.set(0, 0.06, aZ);
    const stock = mesh(new THREE.CapsuleGeometry(0.012, 0.13, 6, 12), gold);
    stock.rotation.z = Math.PI / 2;
    stock.position.set(0, 0.315, aZ);
    mesh(new THREE.TorusGeometry(0.038, 0.011, 12, 40), gold).position.set(0, 0.375, aZ);
    const armCurve = new THREE.CatmullRomCurve3([
        [-0.31, -0.115], [-0.2, -0.205], [-0.08, -0.255], [0, -0.27], [0.08, -0.255], [0.2, -0.205], [0.31, -0.115],
    ].map(([x, y]) => new THREE.Vector3(x, y, aZ)));
    mesh(new THREE.TubeGeometry(armCurve, 80, 0.019, 12), gold);
    const crown = mesh(new THREE.ConeGeometry(0.03, 0.07, 20), gold);
    crown.rotation.z = Math.PI;
    crown.position.set(0, -0.3, aZ);
    const fluke = new THREE.Shape();
    fluke.moveTo(0, 0.075); fluke.lineTo(-0.045, -0.02); fluke.lineTo(0, 0.005); fluke.lineTo(0.045, -0.02); fluke.closePath();
    [[-1, -0.315], [1, 0.315]].forEach(([side, x]) => {
        const f = mesh(extrude(fluke, 0.022, 0.006), gold);
        f.position.set(x, -0.11, aZ - 0.011);
        f.rotation.z = side * -0.75;
    });

    // ------------------------------------------------------------ dolphin around the shank
    const silver = new THREE.MeshPhysicalMaterial({ color: PALETTE.dolphin, roughness: 0.25, metalness: 0.35, clearcoat: 0.8, clearcoatRoughness: 0.15 });
    const bodyCurve = new THREE.CatmullRomCurve3([
        [-0.2, 0.205, 0.16], [-0.12, 0.225, 0.17], [-0.02, 0.215, 0.175], [0.07, 0.17, 0.175],
        [0.08, 0.08, 0.17], [0.03, 0.0, 0.165], [-0.03, -0.07, 0.155], [-0.05, -0.13, 0.15],
    ].map(([x, y, z]) => new THREE.Vector3(x, y, z)), false, 'catmullrom', 0.4);
    const TUB = 120, RAD = 20;
    const bodyGeo = new THREE.TubeGeometry(bodyCurve, TUB, 1, RAD, false);
    const radiusAt = (t) => {
        if (t < 0.08) return 0.012 + (t / 0.08) * 0.02;
        if (t < 0.16) return 0.032 + ((t - 0.08) / 0.08) * 0.022;
        if (t < 0.4) return 0.054 - ((t - 0.16) / 0.24) * 0.004;
        return Math.max(0.008, 0.05 * Math.pow(1 - (t - 0.4) / 0.6, 1.25));
    };
    {
        const pos = bodyGeo.attributes.position;
        const v = new THREE.Vector3(), c = new THREE.Vector3();
        for (let j = 0; j <= TUB; j++) {
            const t = j / TUB;
            bodyCurve.getPointAt(t, c);
            const r = radiusAt(t);
            for (let k = 0; k <= RAD; k++) {
                const i = j * (RAD + 1) + k;
                v.fromBufferAttribute(pos, i).sub(c).multiplyScalar(r);
                v.z *= 0.8;
                v.add(c);
                pos.setXYZ(i, v.x, v.y, v.z);
            }
        }
        bodyGeo.computeVertexNormals();
    }
    mesh(bodyGeo, silver);
    mesh(new THREE.SphereGeometry(0.012, 12, 10), silver).position.copy(bodyCurve.getPointAt(0));
    const finShape = new THREE.Shape();
    finShape.moveTo(-0.035, 0); finShape.quadraticCurveTo(0.0, 0.03, 0.03, 0.075); finShape.quadraticCurveTo(0.025, 0.03, 0.04, 0); finShape.closePath();
    const fin = mesh(extrude(finShape, 0.008, 0.003), silver);
    fin.position.set(0.035, 0.235, 0.168);
    fin.rotation.z = -0.55;
    const pec = mesh(extrude(finShape, 0.006, 0.003), silver);
    pec.scale.set(0.7, 0.7, 1);
    pec.position.set(-0.06, 0.17, 0.2);
    pec.rotation.z = 2.6;
    const tailShape = new THREE.Shape();
    tailShape.moveTo(0, 0); tailShape.quadraticCurveTo(-0.03, -0.01, -0.07, 0.025); tailShape.quadraticCurveTo(-0.035, -0.035, 0, -0.03);
    tailShape.quadraticCurveTo(0.035, -0.035, 0.07, 0.025); tailShape.quadraticCurveTo(0.03, -0.01, 0, 0); tailShape.closePath();
    const tail = mesh(extrude(tailShape, 0.008, 0.003), silver);
    const tailEnd = bodyCurve.getPointAt(1);
    tail.position.set(tailEnd.x, tailEnd.y - 0.005, tailEnd.z - 0.004);
    tail.rotation.z = -0.35;
    mesh(new THREE.SphereGeometry(0.0065, 10, 8), new THREE.MeshStandardMaterial({ color: '#0b1630', roughness: 0.3 })).position.set(-0.135, 0.236, 0.205);

    // back: plain white enamel inside the ring (the seal image shows only the front)
    const back = mesh(new THREE.CircleGeometry(discR, 96), enamel(PALETTE.white));
    back.rotation.y = Math.PI;
    back.position.z = -0.046;

    // ------------------------------------------------------------ size, visibility, loop
    const resize = () => {
        const w = stage.clientWidth, h = stage.clientHeight || w;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };
    resize();
    if ('ResizeObserver' in window) new ResizeObserver(resize).observe(stage);
    else window.addEventListener('resize', resize);

    let onScreen = true;
    new IntersectionObserver((entries) => { onScreen = entries.some((e) => e.isIntersecting); }).observe(stage);

    // first frame now, then swap the image for the canvas (it fades in)
    controls.update();
    renderer.render(scene, camera);
    stage.appendChild(canvas);
    void canvas.offsetWidth; // let the fade start from opacity 0
    stage.classList.add('is-3d');

    const clock = new THREE.Clock();
    renderer.setAnimationLoop(() => {
        if (!onScreen || document.hidden) return;
        const t = clock.getElapsedTime();
        if (!reduce) {
            seal.rotation.y = Math.sin(t * 0.5) * 0.24;
            seal.rotation.x = Math.sin(t * 0.37) * 0.05;
            seal.position.y = Math.sin(t * 0.8) * 0.02;
        }
        controls.update();
        renderer.render(scene, camera);
    });
}
