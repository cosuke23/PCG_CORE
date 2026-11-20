"""
BRP Gabriela Silang (OPV-8301) for the PCG HRMIS landing page.

Run headless:
    blender -b --factory-startup --python tools/blender/brp_gabriela_silang.py -- <out.glb> [<out.blend>] [<preview.png>]

Builds the ship in metres (Blender: X stern -> bow, Y port <- -> starboard is -Y, Z up,
waterline Z = 0) from her published particulars (OCEA OPV 270 Mk II, 84 m x 16 m,
3.5 m draft; Wikipedia, Baird Maritime 2020) and public-domain RIMPAC 2026 photos.
Exports a GLB whose material names the web scene (public/js/pcg-ship-3d.js) maps to
its own materials; paint, lettering, seal and flag are added there as textures.
"""
import math
import sys

import bmesh
import bpy
from mathutils import Matrix, Vector

argv = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else []
OUT_GLB = argv[0] if argv else "brp-gabriela-silang.glb"
OUT_BLEND = argv[1] if len(argv) > 1 else None
OUT_PNG = argv[2] if len(argv) > 2 else None

# ------------------------------------------------------------------ scene and materials
bpy.ops.wm.read_factory_settings(use_empty=True)
scene = bpy.context.scene

MATS = {}


def material(name, rgb, rough=0.45, metal=0.0):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    bsdf = m.node_tree.nodes.get("Principled BSDF")
    bsdf.inputs["Base Color"].default_value = (*rgb, 1.0)
    bsdf.inputs["Roughness"].default_value = rough
    bsdf.inputs["Metallic"].default_value = metal
    m.diffuse_color = (*rgb, 1.0)  # viewport / Workbench colour
    MATS[name] = m
    return m


def srgb(h):
    h = h.lstrip("#")
    c = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    return tuple(((x + 0.055) / 1.055) ** 2.4 if x > 0.04045 else x / 12.92 for x in c)


for name, hexc, r, m in [
    ("HullPaint", "#f2f4f6", 0.35, 0), ("White", "#f2f4f6", 0.38, 0), ("BlueDeck", "#4a6fd0", 0.7, 0),
    ("Dark", "#353c46", 0.75, 0), ("Glass", "#17212c", 0.08, 0.6), ("BridgeGlass", "#17212c", 0.08, 0.6),
    ("Mast", "#eef1f4", 0.4, 0), ("Rail", "#e6eaee", 0.35, 0.5), ("Orange", "#d9822b", 0.6, 0),
    ("RhibGrey", "#5a636d", 0.75, 0), ("Rubber", "#2c3238", 0.8, 0), ("Helideck", "#c3c9d1", 0.8, 0),
    ("HangarDoor", "#aeb6c0", 0.55, 0.3), ("Steel", "#9aa3ad", 0.4, 0.6), ("Radar", "#f4f6f8", 0.5, 0), ("Red", "#c8102e", 0.5, 0), ("Net", "#2a2f36", 0.9, 0),
]:
    material(name, srgb(hexc), r, m)


class Builder:
    """Collects geometry for one object (one or more materials) in a single bmesh."""

    def __init__(self, name):
        self.name = name
        self.bm = bmesh.new()
        self.uv = self.bm.loops.layers.uv.new("UVMap")
        self.mat_index = {}
        self.smooth = False

    def mi(self, mat):
        if mat not in self.mat_index:
            self.mat_index[mat] = len(self.mat_index)
        return self.mat_index[mat]

    def faces_from(self, verts_before, faces_before, mat, uv=None):
        new_faces = [f for f in self.bm.faces if f.index == -1 or f.index >= faces_before]
        return new_faces

    def tag(self, faces, mat, uvfn=None, smooth=False):
        idx = self.mi(mat)
        for f in faces:
            f.material_index = idx
            f.smooth = smooth
            for loop in f.loops:
                co = loop.vert.co
                loop[self.uv].uv = uvfn(co, f) if uvfn else box_uv(co, f.normal)

    def box(self, x0, x1, y0, y1, z0, z1, mat, uvfn=None):
        before = set(self.bm.faces)
        geom = bmesh.ops.create_cube(self.bm, size=1.0)
        sx, sy, sz = x1 - x0, y1 - y0, z1 - z0
        bmesh.ops.transform(self.bm, matrix=Matrix.Translation(((x0 + x1) / 2, (y0 + y1) / 2, (z0 + z1) / 2))
                            @ Matrix.Diagonal((sx, sy, sz, 1)), verts=geom["verts"])
        self.tag([f for f in self.bm.faces if f not in before], mat, uvfn)

    def cylinder(self, p1, p2, r1, r2=None, segs=12, mat="Steel", caps=True, smooth=True):
        r2 = r1 if r2 is None else r2
        p1, p2 = Vector(p1), Vector(p2)
        d = p2 - p1
        if d.length < 1e-6:
            return
        before = set(self.bm.faces)
        geom = bmesh.ops.create_cone(self.bm, cap_ends=caps, cap_tris=False, segments=segs,
                                     radius1=r1, radius2=r2, depth=d.length)
        rot = Vector((0, 0, 1)).rotation_difference(d.normalized()).to_matrix().to_4x4()
        bmesh.ops.transform(self.bm, matrix=Matrix.Translation((p1 + p2) / 2) @ rot, verts=geom["verts"])
        self.tag([f for f in self.bm.faces if f not in before], mat, smooth=smooth)

    def sphere(self, c, r, mat, segs=20, rings=12):
        before = set(self.bm.faces)
        geom = bmesh.ops.create_uvsphere(self.bm, u_segments=segs, v_segments=rings, radius=r)
        bmesh.ops.translate(self.bm, vec=Vector(c), verts=geom["verts"])
        self.tag([f for f in self.bm.faces if f not in before], mat, smooth=True)

    def profile(self, pts_xz, y_half, mat):
        """Side profile (x, z) extruded across the ship from -y_half to +y_half."""
        before = set(self.bm.faces)
        vs_a = [self.bm.verts.new((x, -y_half, z)) for x, z in pts_xz]
        vs_b = [self.bm.verts.new((x, y_half, z)) for x, z in pts_xz]
        self.bm.faces.new(list(reversed(vs_a)))
        self.bm.faces.new(vs_b)
        n = len(pts_xz)
        for i in range(n):
            j = (i + 1) % n
            self.bm.faces.new((vs_a[i], vs_a[j], vs_b[j], vs_b[i]))
        self.tag([f for f in self.bm.faces if f not in before], mat)

    def finish(self, collection=None, bevel=0.0, smooth_angle=None):
        bmesh.ops.recalc_face_normals(self.bm, faces=self.bm.faces)
        me = bpy.data.meshes.new(self.name)
        self.bm.to_mesh(me)
        self.bm.free()
        for mat in self.mat_index:
            me.materials.append(MATS[mat])
        ob = bpy.data.objects.new(self.name, me)
        (collection or scene.collection).objects.link(ob)
        if bevel > 0:
            mod = ob.modifiers.new("Bevel", "BEVEL")
            mod.width = bevel
            mod.segments = 2
            mod.limit_method = "ANGLE"
            mod.angle_limit = math.radians(40)
            mod.harden_normals = True
        if smooth_angle is not None:
            for p in me.polygons:
                p.use_smooth = True
            try:
                me.set_sharp_from_angle(angle=math.radians(smooth_angle))
            except AttributeError:
                pass
        return ob


def box_uv(co, n):
    a = (abs(n.x), abs(n.y), abs(n.z))
    if a[2] >= a[0] and a[2] >= a[1]:
        return (co.x / 4, co.y / 4)
    if a[0] >= a[1]:
        return (co.y / 4, co.z / 4)
    return (co.x / 4, co.z / 4)


# ------------------------------------------------------------------ hull (lofted sections)
X0, X1 = -42.0, 42.2
DECK = 7.0


def deck_z(x):
    if x < 22:
        return DECK + (x - X0) / (22 - X0) * 0.2
    return DECK + 0.2 + ((x - 22) / (X1 - 22)) ** 2 * 1.4


def keel_z(x):
    if x < -34:
        return -3.5 + (-34 - x) / 8 * 2.0
    if x < 29:
        return -3.5
    if x < 37.5:
        return -3.5 + ((x - 29) / 8.5) ** 2 * 3.5
    return (x - 37.5) / (X1 - 37.5) * deck_z(X1)


def half_beam(x):
    if x < -34:
        return 8.0 - ((-34 - x) / 8) ** 2 * 0.4
    if x < 5:
        return 8.0
    return max(0.04, 8.0 * (1 - ((x - 5) / (X1 - 5)) ** 2.0))


def bowness(x):
    return max(0.0, min(1.0, (x - 4) / 36)) ** 1.1


def lerp(a, b, t):
    return a + (b - a) * t


def hull_uv(co, f):
    return ((co.x - X0) / (X1 - X0), (co.z + 4.0) / 14.0)


hull = Builder("Hull")
STATIONS = 200
rings = []
for i in range(STATIONS + 1):
    x = X0 + (X1 - X0) * i / STATIONS
    top = deck_z(x)
    bot = min(keel_z(x), top - 0.01)
    w = half_beam(x)
    b = bowness(x)

    def zc(z):
        return min(top, max(bot, z))

    half = [
        (w, top), (w * 0.997, zc(top - 1.2)), (w * lerp(0.99, 0.80, b), zc(3.0)),
        (w * lerp(0.97, 0.62, b), zc(0.4)), (w * lerp(0.90, 0.44, b), zc(-1.7)),
        (w * lerp(0.55, 0.18, b), zc(-3.1)), (0.0, bot),
    ]
    ring = half + [(-y, z) for y, z in reversed(half[:-1])]
    # Blender Y: the web scene's +z (starboard, facing the camera) is -Y here
    rings.append([hull.bm.verts.new((x, -y, z)) for y, z in ring])
side_faces = []
for i in range(STATIONS):
    a, c = rings[i], rings[i + 1]
    for k in range(len(a) - 1):
        side_faces.append(hull.bm.faces.new((a[k], a[k + 1], c[k + 1], c[k])))
hull.tag(side_faces, "HullPaint", hull_uv, smooth=True)
transom = hull.bm.faces.new(list(rings[0]))
hull.tag([transom], "HullPaint", hull_uv)
hull_ob = hull.finish(smooth_angle=35)

deck = Builder("MainDeck")
dverts = []
for i in range(STATIONS + 1):
    x = X0 + (X1 - X0) * i / STATIONS
    z = min(deck_z(x), DECK + 0.3) + 0.02
    w = half_beam(x) * 0.985
    dverts.append((deck.bm.verts.new((x, w, z)), deck.bm.verts.new((x, -w, z))))
dfaces = [deck.bm.faces.new((dverts[i][0], dverts[i + 1][0], dverts[i + 1][1], dverts[i][1])) for i in range(STATIONS)]
deck.tag(dfaces, "Helideck")
deck.finish()

# bow bulwark: thin wall following the deck edge where the sheer rises
bulwark = Builder("BowBulwark")
for side in (1, -1):
    prev = None
    for i in range(0, 41):
        x = 22 + (X1 - 22 - 0.3) * i / 40
        w = half_beam(x) * 0.985
        top = deck_z(x)
        pts = (bulwark.bm.verts.new((x, side * w, DECK + 0.3)), bulwark.bm.verts.new((x, side * w, top)))
        if prev:
            f = bulwark.bm.faces.new((prev[0], pts[0], pts[1], prev[1]) if side > 0 else (prev[1], pts[1], pts[0], prev[0]))
            bulwark.tag([f], "White")
        prev = pts
bulwark.finish()

# ------------------------------------------------------------------ superstructure
SW = 7.6            # half width (15.2 m)
L1, L2 = 11.2, 13.8  # roof of the main superstructure, roof of the bridge
sup = Builder("Superstructure")
sup.profile([(-13.7, DECK), (2.0, DECK), (2.0, L1), (-13.7, L1)], 3.1, "White")           # hangar core
sup.profile([(-13.7, 9.6), (2.0, 9.6), (2.0, L1), (-13.7, L1)], SW, "White")              # roof over the bays
sup.profile([(2.0, DECK), (8.8, DECK), (8.8, L1), (2.0, L1)], SW, "White")                # forward block
sup.profile([(8.8, DECK), (18.6, DECK), (17.6, 10.0), (8.8, 10.0)], SW - 0.2, "White")   # lower forward deckhouse
sup.profile([(-8.8, L1), (5.8, L1), (6.8, L2), (-8.4, L2)], 6.2, "White")                 # bridge (windows raked forward)
sup_ob = sup.finish(bevel=0.08, smooth_angle=30)

glass = Builder("BridgeWindows")
glass.profile([(-8.6, 12.3), (6.27, 12.3), (6.66, 13.3), (-8.45, 13.3)], 6.3, "BridgeGlass")
glass.finish()

misc = Builder("Fittings")
# bridge wings
for s in (1, -1):
    misc.box(2.5, 6.4, s * 6.2 - 0.4 if s > 0 else -6.9, s * 6.9 if s > 0 else -6.2 + 0.4, 12.0, 13.6, "White")
# blue roof decks
misc.box(-13.5, -9.0, -SW + 0.25, SW - 0.25, L1, L1 + 0.15, "BlueDeck")
misc.box(6.6, 8.6, -SW + 0.4, SW - 0.4, L1, L1 + 0.15, "BlueDeck")
misc.box(9.0, 17.4, -SW + 0.6, SW - 0.6, 10.0, 10.15, "BlueDeck")
misc.box(-8.6, 6.7, -6.1, 6.1, L2, L2 + 0.15, "BlueDeck")
# side windows (slightly proud dark panes)
for s in (1, -1):
    yy = s * (SW + 0.02)
    for x in (3.4, 5.2, 7.0):
        misc.box(x, x + 1.2, yy - 0.03, yy + 0.03, 10.0, 10.8, "Glass")
    for x in (10.5, 12.3, 14.1, 15.9):
        misc.box(x, x + 1.1, yy - 0.03 - s * 0.2, yy + 0.03 - s * 0.2, 8.1, 8.8, "Glass")
# hangar door with ribs
misc.box(-13.85, -13.72, -2.5, 2.5, 7.3, 10.8, "HangarDoor")
for k in range(9):
    z = 7.7 + k * 0.38
    misc.box(-13.9, -13.84, -2.4, 2.4, z, z + 0.05, "Steel")
# boat bays: dark inner walls and bay roof underside
for s in (1, -1):
    misc.box(-13.3, 1.8, s * 3.05 - 0.03, s * 3.05 + 0.03, DECK, 9.6, "Dark")
    misc.box(-13.3, 1.8, min(s * 3.1, s * SW), max(s * 3.1, s * SW), 9.55, 9.6, "Dark")
# helideck plate and aft working deck
misc.box(-33.1, -13.8, -7.6, 7.6, DECK + 0.25, DECK + 0.55, "Helideck",
         uvfn=lambda co, f: ((co.x + 33.1) / 19.3, (co.y + 7.6) / 15.2))
misc.box(-41.8, -33.1, -7.5, 7.5, DECK + 0.02, DECK + 0.2, "BlueDeck")
misc.box(21.0, 33.5, -5.0, 5.0, DECK + 0.32, DECK + 0.45, "BlueDeck")
# deck crane (orange) and life-raft canisters at the stern
misc.cylinder((-37.6, -3.6, DECK), (-37.6, -3.6, DECK + 2.2), 0.6, 0.5, 16, "Orange")
misc.cylinder((-37.6, -3.6, DECK + 2.2), (-33.4, -3.6, DECK + 3.8), 0.22, 0.16, 10, "Orange")
for y in (-5.2, -3.8, 5.5, 4.1):
    misc.cylinder((-40.6, y, DECK + 0.9), (-38.8, y, DECK + 0.9), 0.55, None, 18, "White")
    misc.box(-40.4, -39.0, y - 0.5, y + 0.5, DECK + 0.2, DECK + 0.4, "Steel")
# bow fittings: windlass, bollards, jack staff, anchor hawse pipes
misc.cylinder((31.0, -1.4, DECK + 0.6), (31.0, 1.4, DECK + 0.6), 0.45, None, 18, "Dark")
for x in (25.0, 28.0):
    for s in (1, -1):
        for dx in (0, 0.7):
            misc.cylinder((x + dx, s * 3.4, DECK + 0.4), (x + dx, s * 3.4, DECK + 1.1), 0.18, None, 12, "Dark")
misc.cylinder((41.4, 0, deck_z(41.4)), (41.4, 0, deck_z(41.4) + 3.0), 0.05, 0.03, 8, "Mast")
for s in (1, -1):
    misc.cylinder((32.0, s * 1.2, DECK + 0.4), (32.5, s * 1.5, DECK + 0.4), 0.3, None, 12, "Dark")
# satcom radomes, searchlights, whip antennas on the bridge roof
for x, y in ((3.5, 4.0), (3.5, -4.0), (-7.0, 0.0)):
    misc.cylinder((x, y, L2 + 0.15), (x, y, L2 + 0.8), 0.25, None, 12, "Mast")
    misc.sphere((x, y, L2 + 1.25), 0.65, "Radar")
for s in (1, -1):
    misc.cylinder((5.4, s * 4.0, L2 + 0.15), (5.4, s * 4.0, L2 + 0.6), 0.25, 0.3, 16, "Rubber")
for x, y in ((1.0, 5.0), (-5.5, -5.0), (-2.0, 5.5)):
    misc.cylinder((x, y, L2 + 0.15), (x, y, L2 + 6.0), 0.05, 0.025, 6, "Mast")
misc.finish(smooth_angle=35)

# ------------------------------------------------------------------ railings (round stanchions, top and mid rails)
rails = Builder("Railings")


def rail_run(pts, z0, h=1.05, spacing=1.6):
    """Railing along a polyline of (x, y) at deck height z0."""
    for (xa, ya), (xb, yb) in zip(pts, pts[1:]):
        rails.cylinder((xa, ya, z0 + h), (xb, yb, z0 + h), 0.035, None, 8, "Rail", caps=False)
        rails.cylinder((xa, ya, z0 + h * 0.5), (xb, yb, z0 + h * 0.5), 0.025, None, 6, "Rail", caps=False)
        n = max(1, int(math.hypot(xb - xa, yb - ya) / spacing))
        for i in range(n + 1):
            t = i / n
            x, y = xa + (xb - xa) * t, ya + (yb - ya) * t
            rails.cylinder((x, y, z0), (x, y, z0 + h), 0.03, None, 6, "Rail", caps=False)


for s in (1, -1):
    rail_run([(-33.0, s * 7.55), (-14.0, s * 7.55)], DECK + 0.55)          # helideck edges
    rail_run([(-41.6, s * 7.5), (-33.3, s * 7.5)], DECK + 0.2)              # aft working deck
    rail_run([(-13.5, s * 7.4), (-9.2, s * 7.4)], L1 + 0.15)               # aft roof
    rail_run([(9.0, s * 7.2), (17.3, s * 7.2)], 10.15)                      # lower deckhouse roof
    rail_run([(6.6, s * 7.3), (8.6, s * 7.3)], L1 + 0.15)
rail_run([(-41.6, 7.5), (-41.6, -7.5)], DECK + 0.2)
rail_run([(17.3, 7.2), (17.3, -7.2)], 10.15)
rails.finish()

# ------------------------------------------------------------------ mast (lattice), platforms, yards; radars as separate parts
mast = Builder("Mast")
MX = -2.7
base_z, top_z = L2 + 0.1, 23.6
for dx, dy in ((0.7, 0.7), (0.7, -0.7), (-0.7, 0.7), (-0.7, -0.7)):
    mast.cylinder((MX + dx, dy, base_z), (MX + dx * 0.35, dy * 0.35, top_z), 0.09, 0.06, 8, "Mast")
levels = [base_z + k * 1.2 for k in range(9)]
for za, zb in zip(levels, levels[1:]):
    fa = 1 - (za - base_z) / (top_z - base_z) * 0.65
    fb = 1 - (zb - base_z) / (top_z - base_z) * 0.65
    for (ax, ay), (bx, by) in (((0.7, 0.7), (0.7, -0.7)), ((-0.7, 0.7), (-0.7, -0.7)), ((0.7, 0.7), (-0.7, 0.7)), ((0.7, -0.7), (-0.7, -0.7))):
        mast.cylinder((MX + ax * fa, ay * fa, za), (MX + bx * fb, by * fb, zb), 0.03, None, 6, "Mast", caps=False)
        mast.cylinder((MX + ax * fb, ay * fb, zb), (MX + bx * fb, by * fb, zb), 0.03, None, 6, "Mast", caps=False)
for z, half in ((16.8, 1.8), (19.2, 1.3)):
    mast.box(MX - half, MX + half, -half, half, z - 0.08, z + 0.08, "Mast")
for z, w in ((18.0, 3.2), (21.2, 2.1)):
    mast.cylinder((MX, -w, z), (MX, w, z), 0.08, None, 8, "Mast")
mast.sphere((MX, 0, top_z + 0.6), 0.7, "Radar")
mast.finish(smooth_angle=40)


def radar(name, z, length, size):
    b = Builder(name)
    b.box(-size, size, -length / 2, length / 2, -size * 0.6, size * 0.6, "Radar")
    b.cylinder((0, 0, -size * 0.6), (0, 0, -size * 1.6), size * 0.5, None, 12, "Steel")
    ob = b.finish(bevel=0.04)
    ob.location = (MX, 0, z)
    return ob


radar("RadarS", 17.4, 6.0, 0.28)   # SharpEye S-band
radar("RadarX", 19.7, 3.8, 0.2)    # SharpEye X-band

# ------------------------------------------------------------------ boat bays: RHIBs on davits
for s, nm in ((1, "RhibPort"), (-1, "RhibStarboard")):
    r = Builder(nm)
    y0 = s * 5.2
    L, R = 9.5, 0.55
    # collar tubes along both sides, joined round the bow
    for side in (1, -1):
        r.cylinder((-L / 2 + 0.5, y0 + side * 1.25, DECK + 1.1), (L / 2 - 1.4, y0 + side * 1.25, DECK + 1.1), R, None, 16, "RhibGrey")
        r.cylinder((L / 2 - 1.4, y0 + side * 1.25, DECK + 1.1), (L / 2, y0, DECK + 1.15), R, R * 0.8, 16, "RhibGrey")
    r.box(-L / 2 + 0.4, L / 2 - 1.2, y0 - 1.2, y0 + 1.2, DECK + 0.55, DECK + 1.0, "Rubber")
    r.box(-0.6, 1.0, y0 - 0.5, y0 + 0.5, DECK + 1.0, DECK + 2.0, "White")          # console
    r.box(-L / 2 + 0.2, -L / 2 + 1.0, y0 - 0.45, y0 + 0.45, DECK + 1.0, DECK + 1.9, "Rubber")  # outboard engine
    # davit: post and arm over the boat
    r.cylinder((-0.5, s * 3.3, DECK), (-0.5, s * 3.3, 9.3), 0.18, 0.14, 12, "Rail")
    r.cylinder((-0.5, s * 3.3, 9.3), (-0.5, y0, 9.45), 0.14, None, 12, "Rail")
    r.finish(smooth_angle=40)

# ------------------------------------------------------------------ finer detail (things that read at landing-page distance)
det = Builder("Details")
# sun visor over the raked bridge windows, with a dark underside
det.box(6.55, 7.45, -6.45, 6.45, L2 - 0.02, L2 + 0.12, "White")
det.box(6.55, 7.4, -6.4, 6.4, L2 - 0.06, L2 - 0.02, "Dark")
# mullion bars on the bridge front
for k in range(-5, 6):
    y = k * 1.12
    det.box(6.25, 6.7, y - 0.04, y + 0.04, 12.25, 13.35, "White")
# twin exhaust uptakes on the aft roof, soot-dark tops and side louvres
for s in (1, -1):
    yc = s * 4.6
    det.box(-12.4, -9.8, yc - 0.9, yc + 0.9, L1 + 0.15, L1 + 2.5, "White")
    det.box(-12.3, -9.9, yc - 0.8, yc + 0.8, L1 + 2.5, L1 + 2.62, "Dark")
    for x in (-11.6, -10.6):
        det.cylinder((x, yc, L1 + 2.5), (x, yc, L1 + 2.95), 0.28, 0.26, 14, "Dark")
    for k in range(5):
        z = L1 + 0.6 + k * 0.32
        det.box(-12.2, -10.0, yc + s * 0.9 - 0.02, yc + s * 0.9 + 0.04, z, z + 0.12, "Dark")
# watertight doors and portholes on the superstructure sides
for s in (1, -1):
    for x0, yy in ((2.5, SW), (9.3, SW - 0.2)):
        y = s * (yy + 0.02)
        det.box(x0, x0 + 0.9, y - 0.04, y + 0.04, DECK + 0.2, DECK + 2.1, "Steel")
        det.box(x0 + 0.65, x0 + 0.78, y + s * 0.03, y + s * 0.09, DECK + 1.0, DECK + 1.15, "Dark")
    for x in (4.2, 5.4, 6.6, 7.8):
        y = s * (SW + 0.01)
        det.cylinder((x, y, 8.4), (x, y + s * 0.05, 8.4), 0.2, None, 12, "Glass")
    for x in (11.0, 12.6, 14.2, 15.8):
        y = s * (SW - 0.2 + 0.03)
        det.box(x, x + 0.5, y - 0.03, y + 0.03, 9.2, 9.6, "Glass")
# life-raft canister racks on the 01-deck roof, both sides
for s in (1, -1):
    det.box(10.0, 15.6, s * 6.3 - 0.45, s * 6.3 + 0.45, 10.15, 10.3, "Steel")
    for x in (10.6, 12.0, 13.4, 14.8):
        det.cylinder((x - 0.55, s * 6.3, 10.85), (x + 0.55, s * 6.3, 10.85), 0.5, None, 18, "White")
        det.cylinder((x - 0.05, s * 6.3, 10.85), (x + 0.05, s * 6.3, 10.85), 0.52, None, 18, "Dark")
# fire-fighting monitors on the forward deckhouse roof
for s in (1, -1):
    det.cylinder((16.6, s * 2.4, 10.15), (16.6, s * 2.4, 11.2), 0.22, 0.18, 14, "Red")
    det.sphere((16.6, s * 2.4, 11.3), 0.26, "Red", 14, 8)
    det.cylinder((16.6, s * 2.4, 11.35), (17.7, s * 2.4, 11.7), 0.09, 0.06, 10, "Red")
# helideck: perimeter safety nets outboard of the edge, landing lights
for s in (1, -1):
    det.box(-33.0, -14.0, min(s * 7.6, s * 8.6), max(s * 7.6, s * 8.6), DECK + 0.42, DECK + 0.48, "Net")
    for x in (-32.0, -28.0, -24.0, -20.0, -16.0):
        det.cylinder((x, s * 7.4, DECK + 0.55), (x, s * 7.4, DECK + 0.68), 0.09, None, 8, "Orange")
# mooring gear: stern bitts, fairleads and capstans; forecastle fairleads
for s in (1, -1):
    for x in (-40.2, -35.0):
        for dx in (0, 0.7):
            det.cylinder((x + dx, s * 6.4, DECK + 0.2), (x + dx, s * 6.4, DECK + 0.85), 0.17, None, 12, "Dark")
        det.box(x - 0.2, x + 0.9, s * 6.4 - 0.2, s * 6.4 + 0.2, DECK + 0.2, DECK + 0.3, "Dark")
    det.cylinder((-38.0, s * 1.6, DECK + 0.2), (-38.0, s * 1.6, DECK + 0.9), 0.38, 0.3, 16, "Dark")
    det.box(-41.9, -41.4, s * 6.4 - 0.5, s * 6.4 + 0.5, DECK + 0.2, DECK + 0.65, "Steel")
    det.box(23.5, 24.3, s * 4.2 - 0.25, s * 4.2 + 0.25, DECK + 0.45, DECK + 0.9, "Steel")
    # anchor chains from the windlass to the hawse pipes
    det.cylinder((31.0, s * 1.2, DECK + 0.62), (32.3, s * 1.45, DECK + 0.5), 0.12, None, 8, "Dark")
# anchors stowed in the hull recesses (stockless: shank and flukes)
for s in (1, -1):
    x = 32.2
    y = s * (half_beam(x) * 0.99 + 0.08)
    det.box(x - 0.12, x + 0.12, y - 0.08, y + 0.08, 5.15, 6.75, "Dark")
    det.box(x - 0.55, x + 0.55, y - 0.1, y + 0.1, 4.75, 5.2, "Dark")
    det.cylinder((x - 0.55, y, 5.0), (x - 0.75, y, 5.5), 0.11, 0.05, 8, "Dark")
    det.cylinder((x + 0.55, y, 5.0), (x + 0.75, y, 5.5), 0.11, 0.05, 8, "Dark")
# rubbing strakes along the parallel body
for s in (1, -1):
    det.cylinder((-33.0, s * 8.04, 5.9), (4.0, s * 8.04, 5.9), 0.1, None, 8, "White")
    det.cylinder((-33.0, s * 8.03, 2.4), (-6.0, s * 8.03, 2.4), 0.08, None, 8, "White")
# forecastle railing following the deck edge, and a breakwater ahead of the deckhouse
for s in (1, -1):
    pts = [(18.8 + (40.6 - 18.8) * i / 12) for i in range(13)]
    for xa, xb in zip(pts, pts[1:]):
        ya, yb = s * half_beam(xa) * 0.97, s * half_beam(xb) * 0.97
        za, zb = deck_z(xa) + 1.0, deck_z(xb) + 1.0
        det.cylinder((xa, ya, za), (xb, yb, zb), 0.035, None, 6, "Rail", caps=False)
        det.cylinder((xa, ya, deck_z(xa)), (xa, ya, za), 0.03, None, 6, "Rail", caps=False)
det.profile([(19.2, DECK + 0.3), (20.4, DECK + 0.3), (19.6, DECK + 1.3)], 4.6, "White")
# mast: navigation lights on the yards, horn, extra whip antennas, platform guard rails
for z, w in ((18.0, 3.2), (21.2, 2.1)):
    for s in (1, -1):
        det.box(MX - 0.12, MX + 0.12, s * w - 0.12, s * w + 0.12, z + 0.08, z + 0.35, "Dark")
        det.cylinder((MX, s * w, z), (MX, s * w, z + 2.4), 0.03, 0.015, 6, "Mast")
for z, half in ((16.8, 1.8), (19.2, 1.3)):
    for (xa, ya), (xb, yb) in (((-1, -1), (1, -1)), ((1, -1), (1, 1)), ((1, 1), (-1, 1)), ((-1, 1), (-1, -1))):
        det.cylinder((MX + xa * half, ya * half, z + 0.55), (MX + xb * half, yb * half, z + 0.55), 0.025, None, 6, "Rail", caps=False)
det.cylinder((MX + 0.8, 0, 22.4), (MX + 1.5, 0, 22.4), 0.12, 0.25, 12, "Dark")
for x, y in ((MX + 1.0, 0.8), (MX + 1.0, -0.8)):
    det.cylinder((x, y, top_z - 0.5), (x, y, top_z + 3.2), 0.03, 0.012, 6, "Mast")
# boarding ladder rungs on the hull side under the bays
for s in (1, -1):
    for k in range(8):
        z = 1.2 + k * 0.6
        det.box(-6.4, -5.6, s * 8.02 - 0.03, s * 8.02 + 0.03, z, z + 0.06, "Steel")
det.finish(smooth_angle=35)

# ------------------------------------------------------------------ gaff for the flag (the flag itself is cloth in the web scene)
gaff = Builder("Gaff")
gaff.cylinder((MX - 0.4, 0, 19.6), (MX - 5.6, 0, 21.2), 0.08, 0.05, 8, "Mast")
gaff.finish()

# ------------------------------------------------------------------ export
for ob in scene.objects:
    ob.select_set(True)
bpy.ops.export_scene.gltf(
    filepath=OUT_GLB, export_format="GLB", use_selection=False, export_apply=True,
    export_yup=True, export_texcoords=True, export_normals=True, export_materials="EXPORT",
    export_cameras=False, export_lights=False, export_extras=False,
)
stats = {"objects": len(scene.objects), "triangles": 0}
dg = bpy.context.evaluated_depsgraph_get()
for ob in scene.objects:
    if ob.type == "MESH":
        me = ob.evaluated_get(dg).to_mesh()
        me.calc_loop_triangles()
        stats["triangles"] += len(me.loop_triangles)
        ob.evaluated_get(dg).to_mesh_clear()
print("STATS", stats)

if OUT_BLEND:
    bpy.ops.wm.save_as_mainfile(filepath=OUT_BLEND)

if OUT_PNG:
    # quick look-check render (Workbench, studio light) from the camera angle of the aerial photo
    cam_data = bpy.data.cameras.new("Cam")
    cam_data.lens = 40
    cam = bpy.data.objects.new("Cam", cam_data)
    scene.collection.objects.link(cam)
    cam.location = (30, -95, 62)
    direction = Vector((0, 0, 8)) - cam.location
    cam.rotation_euler = direction.to_track_quat("-Z", "Y").to_euler()
    scene.camera = cam
    scene.render.engine = "BLENDER_WORKBENCH"
    scene.display.shading.light = "STUDIO"
    scene.display.shading.color_type = "MATERIAL"
    scene.display.shading.show_shadows = True
    scene.display.shading.show_cavity = True
    scene.render.resolution_x, scene.render.resolution_y = 1600, 900
    scene.render.filepath = OUT_PNG
    bpy.ops.render.render(write_still=True)
