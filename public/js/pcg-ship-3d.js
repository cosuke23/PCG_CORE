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

    // the visible sky: deep blue overhead fading to a pale hazy horizon, the sun with its glow, and
    // fair-weather cumulus projected onto a layer above the sea, drifting with the wind: bright
    // sunlit tops, grey-blue bases, flattening and dissolving into haze toward the horizon.
    // (The Sky model above only lights the scene; see envSky below.)
    const SKY_ZENITH = new THREE.Color('#1d5cc0'), SKY_HORIZON = new THREE.Color('#bdd6ee');
    const clouds = new THREE.Mesh(new THREE.SphereGeometry(450, 48, 32), new THREE.ShaderMaterial({
        uniforms: {
            uTime: { value: 0 }, uSun: { value: sun.clone().normalize() },
            uZenith: { value: SKY_ZENITH }, uHorizon: { value: SKY_HORIZON },
        },
        vertexShader: `
            varying vec3 vDir;
            void main() {
                vDir = normalize(position);
                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }`,
        fragmentShader: `
            uniform float uTime;
            uniform vec3 uSun, uZenith, uHorizon;
            varying vec3 vDir;
            float hash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
            float noise(vec2 p) {
                vec2 i = floor(p), f = fract(p);
                f = f * f * (3.0 - 2.0 * f);
                return mix(mix(hash(i), hash(i + vec2(1, 0)), f.x), mix(hash(i + vec2(0, 1)), hash(i + vec2(1, 1)), f.x), f.y);
            }
            float fbm(vec2 p) {
                float v = 0.0, a = 0.5;
                for (int i = 0; i < 5; i++) { v += a * noise(p); p = p * 2.07 + vec2(17.0, 9.0); a *= 0.5; }
                return v;
            }
            float cloud(vec2 uv) {
                vec2 warp = vec2(fbm(uv * 0.6 + 3.1), fbm(uv * 0.6 - 5.7)) - 0.5;
                float d = fbm(uv + warp * 1.4);
                float cells = smoothstep(0.35, 0.75, noise(uv * 0.35 + 11.0));   // gaps between cloud groups
                return smoothstep(0.49, 0.7, d * (0.8 + cells * 0.4));
            }
            void main() {
                vec3 d = normalize(vDir);
                float h = max(d.y, 0.0);
                vec3 col = mix(uHorizon, uZenith, pow(h, 0.28)) * 1.75;
                float sd = max(dot(d, uSun), 0.0);
                col += vec3(1.0, 0.95, 0.85) * (pow(sd, 900.0) * 40.0 + pow(sd, 18.0) * 0.5 + pow(sd, 6.0) * 0.06);
                if (d.y > 0.004) {
                    vec2 uv = d.xz / (d.y + 0.1) * 1.3 + vec2(uTime * 0.004, uTime * 0.0016);
                    float c = cloud(uv);
                    if (c > 0.001) {
                        float lit = clamp(0.55 + (c - cloud(uv + uSun.xz * 0.06)) * 2.5, 0.0, 1.0);
                        vec3 cc = mix(vec3(0.5, 0.56, 0.66), vec3(1.0, 0.99, 0.96), lit) * 2.6;
                        cc += vec3(1.0, 0.92, 0.8) * pow(sd, 6.0) * 0.8 * (1.0 - c);   // silver lining near the sun
                        float fade = smoothstep(0.0, 0.22, d.y);
                        col = mix(col, mix(uHorizon * 1.75, cc, fade), c * (0.35 + 0.6 * fade));
                    }
                }
                gl_FragColor = vec4(col, 1.0);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }`,
        side: THREE.BackSide, depthWrite: false,
    }));
    clouds.renderOrder = -1;
    scene.add(clouds);
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

    // ------------------------------------------------------------ ocean (wind sea drawn by a shader)
    // 32 directional waves from about 60 m down to ripples, moving at deep-water speeds
    // (omega = sqrt(g k), g = 0.98 units/s^2 at 1 unit = 10 m). Per pixel: wave slopes give the
    // normal, a Fresnel blend of the deep-water colour and the sky, sun glitter, lighter crests,
    // and haze toward the horizon. Waves finer than a pixel fade out so the sea never shimmers.
    const WAVES = 32;
    const waveData = [];
    {
        let seed = 8301;
        const rnd = () => { seed = (seed * 16807) % 2147483647; return seed / 2147483647; };
        const wind = 0.75;                                     // radians, waves run mostly one way
        for (let i = 0; i < WAVES; i++) {
            const lambda = 6.0 * Math.pow(0.83, i);            // units (x 10 m)
            const k = 2 * Math.PI / lambda;
            const dir = wind + (rnd() - 0.5) * (1.1 + i * 0.06);
            const slope = 0.05 * Math.pow(0.975, i) * (0.7 + rnd() * 0.6);
            waveData.push(new THREE.Vector4(Math.cos(dir), Math.sin(dir), k, slope / k));
        }
    }
    const linear = (hex) => new THREE.Color(hex);
    const water = new THREE.Mesh(new THREE.PlaneGeometry(900, 900), new THREE.ShaderMaterial({
        uniforms: {
            uTime: { value: 0 },
            uWaves: { value: waveData },
            uSunDir: { value: sun.clone().normalize() },
            uSunColor: { value: linear('#fff1d6') },
            uDeep: { value: linear('#05224d') },
            uScatter: { value: linear('#13698f') },
            uSkyHorizon: { value: SKY_HORIZON.clone().multiplyScalar(1.7) },
            uSkyZenith: { value: SKY_ZENITH.clone().multiplyScalar(1.3) },
        },
        vertexShader: `
            varying vec3 vWorld;
            void main() {
                vec4 w = modelMatrix * vec4(position, 1.0);
                vWorld = w.xyz;
                gl_Position = projectionMatrix * viewMatrix * w;
            }`,
        fragmentShader: `
            #define NW ${WAVES}
            uniform float uTime;
            uniform vec4 uWaves[NW];
            uniform vec3 uSunDir, uSunColor, uDeep, uScatter, uSkyHorizon, uSkyZenith;
            varying vec3 vWorld;
            void main() {
                vec2 p = vWorld.xz;
                float dist = length(cameraPosition - vWorld);
                float foot = max(length(fwidth(p)), 1e-4);
                vec2 grad = vec2(0.0);
                float h = 0.0;
                for (int i = 0; i < NW; i++) {
                    vec4 w = uWaves[i];
                    float k = w.z;
                    float a = w.w * (1.0 - smoothstep(0.3, 1.1, k * foot));
                    float ph = k * dot(w.xy, p) - sqrt(0.98 * k) * uTime + float(i) * 1.731;
                    grad += a * k * cos(ph) * w.xy;
                    h += a * k * sin(ph);
                }
                vec3 n = normalize(vec3(-grad.x, 1.0, -grad.y));
                vec3 V = normalize(cameraPosition - vWorld);
                vec3 R = reflect(-V, n);
                R.y = abs(R.y);
                float fres = 0.02 + 0.98 * pow(1.0 - max(dot(n, V), 0.0), 5.0);
                vec3 sky = mix(uSkyHorizon, uSkyZenith, pow(clamp(R.y, 0.0, 1.0), 0.5));
                float crest = clamp(h * 2.2 + 0.25, 0.0, 1.0);
                vec3 body = mix(uDeep, uScatter, crest * 0.45 + max(dot(n, uSunDir), 0.0) * 0.12);
                float sd = max(dot(R, uSunDir), 0.0);
                float glint = pow(sd, 1400.0) * 90.0 + pow(sd, 160.0) * 2.0;
                vec3 col = mix(body, sky, fres) + uSunColor * glint;
                float haze = 1.0 - exp(-dist * 0.0045);
                col = mix(col, uSkyHorizon, haze * haze);
                gl_FragColor = vec4(col, 1.0);
                #include <tonemapping_fragment>
                #include <colorspace_fragment>
            }`,
    }));
    water.rotation.x = -Math.PI / 2;
    scene.add(water);

    // soft contact shadow under the hull (the shader sea does not take shadow maps)
    const shadowTex = (() => {
        const c = document.createElement('canvas'); c.width = 256; c.height = 64;
        const g = c.getContext('2d'), w = c.width, h = c.height;
        const grd = g.createRadialGradient(w / 2, h / 2, 2, w / 2, h / 2, w / 2);
        grd.addColorStop(0, 'rgba(0,8,20,0.55)'); grd.addColorStop(1, 'rgba(0,8,20,0)');
        g.fillStyle = grd; g.fillRect(0, 0, w, h);
        return new THREE.CanvasTexture(c);
    })();
    const hullShadow = new THREE.Mesh(new THREE.PlaneGeometry(10.5, 2.6), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false }));
    hullShadow.rotation.x = -Math.PI / 2; hullShadow.position.set(0.1, 0.006, 0.25); scene.add(hullShadow);

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
        // weathering: faint plate seams, grime just above the waterline, rust streaks under openings
        g.globalAlpha = 0.07; g.strokeStyle = '#5b6470'; g.lineWidth = 2;
        for (let x = X0 + 0.24; x < X1; x += 0.24) { const [a] = px(x, 0); g.beginPath(); g.moveTo(a, 0); g.lineTo(a, H); g.stroke(); }
        [0.3, 0.52].forEach((y) => { const [, b] = px(0, y); g.beginPath(); g.moveTo(0, b); g.lineTo(W, b); g.stroke(); });
        g.globalAlpha = 1;
        { const [, top] = px(0, 0.16), [, bot] = px(0, 0.05);
          const grd = g.createLinearGradient(0, top, 0, bot); grd.addColorStop(0, 'rgba(90,96,80,0)'); grd.addColorStop(1, 'rgba(90,96,80,0.28)');
          g.fillStyle = grd; g.fillRect(0, top, W, bot - top); }
        const streak = (x, y0, len, a = 0.16, color = '120,82,52') => {
            const [sx, sy] = px(x, y0), [, ey] = px(x, y0 - len);
            const grd = g.createLinearGradient(0, sy, 0, ey); grd.addColorStop(0, `rgba(${color},${a})`); grd.addColorStop(1, `rgba(${color},0)`);
            g.fillStyle = grd; g.fillRect(sx - 3, sy, 6, ey - sy);
        };
        [3.25, 3.3].forEach((x) => streak(x, 0.43, 0.3, 0.2));                    // below the anchor recess
        [-2.95, -2.84, -0.95, -0.84, 2.84, 2.95].forEach((x) => streak(x, 0.57, 0.2, 0.1, '70,78,88'));
        for (let x = -3.9; x < 0.4; x += 0.55) streak(x + (Math.sin(x * 7) * 0.05), 0.68, 0.22, 0.06, '70,78,88');
        poly([[-4.4, -0.5], [-4.4, 0.21], [-2.45, 0.21], [-2.35, 0.05], [4.4, 0.05], [4.4, -0.5]], C.boot);   // dark bottom, deep band at the stern
        [-2.3, -2.0, -1.7].forEach((x) => line(x, 0.05, x + 0.26, 0.31, 10));                          // slanted aft accents
        line(-2.42, 0.31, -0.62, 0.31, 7); line(0.02, 0.28, 0.95, 0.28, 7); line(0.2, 0.56, 1.28, 0.56, 6);    // black lines
        [-0.62, -0.18].forEach((x) => line(x, 0.1, x, 0.66, 4, '#9aa3ad'));                              // shell door seams
        poly([[1.13, 0.05], [1.25, 0.05], [1.95, 1.0], [1.83, 1.0]], C.stripeBlue);                      // bow stripes
        poly([[1.33, 0.05], [1.6, 0.05], [2.34, 1.0], [2.07, 1.0]], C.stripeRed);
        poly([[3.21, 0.43], [3.36, 0.43], [3.43, 0.75], [3.28, 0.75]], '#151b22');                       // anchor pocket: tall slot raked with the stem
        poly([[3.235, 0.46], [3.335, 0.46], [3.395, 0.72], [3.295, 0.72]], '#2e3843');
        g.fillStyle = '#2a3340';                                                                          // portholes and small windows
        [2.74, 2.86].forEach((x) => { const [a, b] = px(x, 0.79); g.fillRect(a - 26, b - 13, 52, 26); });          // two small windows
        [3.03, 3.13].forEach((x) => { const [a, b] = px(x, 0.8); g.beginPath(); g.arc(a, b, 15, 0, Math.PI * 2); g.fill(); });  // then two portholes
        [[-2.95, 0.6], [-2.84, 0.6], [-0.95, 0.6], [-0.84, 0.6]].forEach(([x, y]) => { const [a, b] = px(x, y); g.fillRect(a - 9, b - 6, 18, 12); });
    });
    hullTex.flipY = false;
    // helideck paint as in the photos (UV u = aft -> forward over 19.3 m, v = starboard -> port
    // over 15.2 m): dark grey non-slip deck with wear, a white touchdown circle with a white
    // line-up line forward along the centreline, and tie-down points
    const heliTex = canvasTex(1280, 1008, (g, w, h) => {
        const k = w / 19.3;                                   // pixels per metre
        g.fillStyle = '#4f565c'; g.fillRect(0, 0, w, h);
        for (let i = 0; i < 9000; i++) {                      // non-slip grit
            const v = Math.random() < 0.5 ? 'rgba(255,255,255,0.035)' : 'rgba(0,0,0,0.05)';
            g.fillStyle = v; g.fillRect(Math.random() * w, Math.random() * h, 2, 2);
        }
        g.lineCap = 'round';
        for (let i = 0; i < 26; i++) {                        // tyre scuffs and stains
            const cx = w * (0.3 + Math.random() * 0.4), cy = h * (0.25 + Math.random() * 0.5), r = k * (1 + Math.random() * 3);
            g.strokeStyle = `rgba(20,22,24,${(0.08 + Math.random() * 0.1).toFixed(2)})`; g.lineWidth = k * (0.15 + Math.random() * 0.2);
            const a = Math.random() * Math.PI * 2; g.beginPath(); g.arc(cx, cy, r, a, a + 0.4 + Math.random()); g.stroke();
        }
        for (let i = 0; i < 6; i++) {
            const grd = g.createRadialGradient(0, 0, 0, 0, 0, k * 1.2); grd.addColorStop(0, 'rgba(25,25,20,0.16)'); grd.addColorStop(1, 'rgba(25,25,20,0)');
            g.save(); g.translate(w * Math.random(), h * Math.random()); g.fillStyle = grd; g.fillRect(-k * 1.2, -k * 1.2, k * 2.4, k * 2.4); g.restore();
        }
        const cx = w * 0.47, cy = h / 2;
        g.strokeStyle = '#eef0f2'; g.lineWidth = k * 0.5; g.beginPath(); g.arc(cx, cy, k * 4.0, 0, Math.PI * 2); g.stroke();  // touchdown circle
        g.lineWidth = k * 0.3; g.beginPath(); g.moveTo(cx + k * 4.0, cy); g.lineTo(w - k * 0.6, cy); g.stroke();                // line-up line forward
        g.fillStyle = '#25292d';                                                                                              // tie-down points
        for (let ring = 1; ring <= 3; ring++) {
            const r = k * (ring * 1.6 + (ring === 3 ? 0.7 : 0)), n = ring * 8;
            for (let i = 0; i < n; i++) { const a = (i / n) * Math.PI * 2; g.beginPath(); g.arc(cx + Math.cos(a) * r, cy + Math.sin(a) * r, k * 0.09, 0, Math.PI * 2); g.fill(); }
        }
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
        Red: paint('#c8102e'),
        Green: mat('#2fbf5a', { emissive: '#2fbf5a', emissiveIntensity: 0.7 }),
        Lamp: mat('#fff6d8', { emissive: '#fff1c4', emissiveIntensity: 0.8, roughness: 0.2 }),
        Net: mat('#2a2f36', { roughness: 0.95, transparent: true, opacity: 0.75 }),
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
    sideDecal(textTex('GABRIELA SILANG', '700 {s}px Inter, Arial, sans-serif', '#e9edf1', 10, '#1b1f25'), { width: 0.5, height: 0.05, x: -0.39, y: 1.06, z: 0.764 });   // black name board under the bridge
    const bagong = new THREE.TextureLoader().load(new URL('../images/brand/bagong-pilipinas.webp', import.meta.url).href);
    bagong.colorSpace = THREE.SRGBColorSpace;
    sideDecal(bagong, { width: 0.11, height: 0.103, x: 0.14, y: 1.05, z: 0.7955 });   // Bagong Pilipinas logo on the box under the bridge, both sides
    const sternName = new THREE.Mesh(new THREE.PlaneGeometry(0.6, 0.06), new THREE.MeshStandardMaterial({ map: textTex('GABRIELA SILANG', '700 {s}px Inter, Arial, sans-serif', '#1b1f25', 10), transparent: true, polygonOffset: true, polygonOffsetFactor: -2 }));
    sternName.position.set(X0 - 0.004, 0.35, 0); sternName.rotation.y = -Math.PI / 2; ship.add(sternName);
    const homePort = new THREE.Mesh(new THREE.PlaneGeometry(0.26, 0.04), new THREE.MeshStandardMaterial({ map: textTex('MANILA', '700 {s}px Inter, Arial, sans-serif', '#1b1f25', 6.5), transparent: true, polygonOffset: true, polygonOffsetFactor: -2 }));
    homePort.position.set(X0 - 0.004, 0.285, 0); homePort.rotation.y = -Math.PI / 2; ship.add(homePort);

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
    flag.castShadow = true; flag.position.set(-0.67, 2.06, 0); ship.add(flag);  // gaff tip in the model: x -6.7 m, z 21.8 m

    // ------------------------------------------------------------ wake and bow waves
    const foamTex = canvasTex(1024, 256, (g, w, h) => {
        // churned water behind the stern: soft overlapping blobs, dense and narrow near the ship,
        // thinning and spreading downstream, with a few brighter streaks
        const gauss = () => (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
        for (let i = 0; i < 3400; i++) {
            const x = w * Math.pow(Math.random(), 1.5), spread = 26 + x * 0.16;
            const y = h / 2 + gauss() * spread, r = 3 + Math.random() * (5 + x * 0.01);
            const a = Math.max(0, 1 - x / w) ** 1.2 * (0.3 + Math.random() * 0.45);
            const grd = g.createRadialGradient(x, y, 0, x, y, r);
            grd.addColorStop(0, `rgba(255,255,255,${a.toFixed(3)})`); grd.addColorStop(1, 'rgba(255,255,255,0)');
            g.fillStyle = grd; g.fillRect(x - r, y - r, r * 2, r * 2);
        }
        g.strokeStyle = 'rgba(255,255,255,0.12)'; g.lineWidth = 1.5;
        for (let i = 0; i < 40; i++) {
            const y = h / 2 + gauss() * 30, len = 80 + Math.random() * 300;
            g.beginPath(); g.moveTo(0, y); g.bezierCurveTo(len / 3, y + gauss() * 10, len * 0.66, y + gauss() * 16, len, y + gauss() * 22); g.stroke();
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
        water.material.uniforms.uTime.value = t;
        clouds.material.uniforms.uTime.value = t;
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
