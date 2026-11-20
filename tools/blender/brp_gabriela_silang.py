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
    ("HangarDoor", "#aeb6c0", 0.55, 0.3), ("Steel", "#9aa3ad", 0.4, 0.6), ("Radar", "#f4f6f8", 0.5, 0), ("Red", "#c8102e", 0.5, 0), ("Green", "#2fbf5a", 0.3, 0), ("Lamp", "#fff6d8", 0.2, 0), ("Net", "#2a2f36", 0.9, 0),
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


def bulwark_h(x):
    t = max(0.0, min(1.0, (x - 29.0) / (X1 - 29.0)))
    return 1.15 + t ** 1.6 * 1.0



def fore_h(x):
    """Height of the forecastle bulwark above the deck edge (0 aft of the deckhouse front)."""
    return bulwark_h(x) * max(0.0, min(1.0, (x - 18.0) / 0.6))


hull = Builder("Hull")
STATIONS = 200
rings = []
for i in range(STATIONS + 1):
    x = X0 + (X1 - X0) * i / STATIONS
    top = deck_z(x) + fore_h(x)
    bot = min(keel_z(x), deck_z(x) - 0.01)
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
    for i in range(0, 61):
        x = 18.05 + (X1 - 18.05 - 0.3) * i / 60
        w = half_beam(x) * 0.985
        wo = half_beam(x) * 0.997
        top = deck_z(x) + fore_h(x)
        pts = (bulwark.bm.verts.new((x, side * w, DECK + 0.3)), bulwark.bm.verts.new((x, side * w, top)),
               bulwark.bm.verts.new((x, side * wo, top)))
        if prev:
            if side > 0:
                fs = [bulwark.bm.faces.new((prev[0], pts[0], pts[1], prev[1])), bulwark.bm.faces.new((prev[1], pts[1], pts[2], prev[2]))]
            else:
                fs = [bulwark.bm.faces.new((prev[1], pts[1], pts[0], prev[0])), bulwark.bm.faces.new((prev[2], pts[2], pts[1], prev[1]))]
            bulwark.tag(fs, "White")
        prev = pts
bulwark.finish()

# ------------------------------------------------------------------ superstructure
SW = 7.6            # half width (15.2 m)
L1, L2 = 11.2, 13.8  # roof of the main superstructure, roof of the bridge
sup = Builder("Superstructure")
sup.profile([(-13.7, DECK), (2.0, DECK), (2.0, L1), (-13.7, L1)], 3.1, "White")           # hangar core
sup.profile([(-13.7, 9.6), (2.0, 9.6), (2.0, L1), (-13.7, L1)], SW, "White")              # roof over the bays
sup.profile([(2.0, DECK), (8.8, DECK), (8.8, L1), (2.0, L1)], SW, "White")                # forward block
sup_ob = sup.finish(bevel=0.08, smooth_angle=30)

def prism(b, plan0, plan1, z0, z1, mat, caps=(False, True)):
    """Walls between two closed plan outlines (x, y) at heights z0 and z1, optional caps."""
    before = set(b.bm.faces)
    r0 = [b.bm.verts.new((x, y, z0)) for x, y in plan0]
    r1 = [b.bm.verts.new((x, y, z1)) for x, y in plan1]
    n = len(r0)
    for i in range(n):
        j = (i + 1) % n
        b.bm.faces.new((r0[i], r0[j], r1[j], r1[i]))
    if caps[0]:
        b.bm.faces.new(list(reversed(r0)))
    if caps[1]:
        b.bm.faces.new(r1)
    b.tag([f for f in b.bm.faces if f not in before], mat)


def rounded_plan(x0, x1, hy, rf, ra, n=6):
    """Rectangle in plan with rounded forward (rf) and aft (ra) corners, counter-clockwise."""
    pts = []
    for cx, cy, r, a0 in ((x1 - rf, -hy + rf, rf, -90), (x1 - rf, hy - rf, rf, 0), (x0 + ra, hy - ra, ra, 90), (x0 + ra, -hy + ra, ra, 180)):
        for k in range(n + 1):
            a = math.radians(a0 + 90 * k / n)
            pts.append((cx + r * math.cos(a), cy + r * math.sin(a)))
    return pts


# bridge (from photos): rounded front corners with wrap-around windows, raked forward, and a
# white roof with an overhanging rim
BR_X0, BR_X1, BR_HY, BR_RF, BR_RA, BR_RAKE = -8.8, 6.0, 6.2, 2.6, 0.5, 0.5


def br_plan(z, grow=0.0, n=6):
    t = (z - L1) / (L2 - L1)
    pts = rounded_plan(BR_X0 - grow, BR_X1 + grow, BR_HY + grow, BR_RF + grow, BR_RA + grow, n)
    return [(x + BR_RAKE * t * max(0.0, min(1.0, (x - (BR_X1 - BR_RF)) / BR_RF)), y) for x, y in pts]


bridge = Builder("Bridge")
prism(bridge, br_plan(L1), br_plan(L2), L1, L2, "White", caps=(False, False))
bridge.finish(smooth_angle=50)
glass = Builder("BridgeWindows")
prism(glass, br_plan(12.3, 0.04), br_plan(13.3, 0.04), 12.3, 13.3, "BridgeGlass", caps=(False, False))
glass.finish()
roof = Builder("BridgeRoof")
prism(roof, br_plan(L2, 0.3), br_plan(L2, 0.3), L2 - 0.02, L2 + 0.18, "White", caps=(True, True))
roof.finish(smooth_angle=50)

# forward deckhouse (from photos): one tall smooth block up to the bridge base, front raked and
# rounded in plan, white roof; small round portholes on the sides
FDH_X0, FDH_X1, FDH_TOP_X1, FDH_HY, FDH_RF = 8.8, 18.6, 16.4, SW - 0.2, 2.5
fdh = Builder("ForeDeckhouse")
prism(fdh, rounded_plan(FDH_X0, FDH_X1, FDH_HY, FDH_RF, 0.05), rounded_plan(FDH_X0, FDH_TOP_X1, FDH_HY, FDH_RF, 0.05),
      DECK, L1, "White", caps=(False, True))
for s in (1, -1):
    for x, z in ((10.0, 10.3), (11.0, 10.3), (12.8, 8.0), (13.6, 8.0)):
        fdh.cylinder((x, s * FDH_HY, z), (x, s * (FDH_HY + 0.04), z), 0.17, None, 12, "Glass")
fdh.finish(smooth_angle=50)

# aft ends of the boat bays: raked white bulkheads (photo), and the aft roof railing across the back
ab = Builder("BayAftWalls")
for s in (1, -1):
    y0, y1 = sorted((s * 3.1, s * (SW - 0.02)))
    before = set(ab.bm.faces)
    pts = [(-13.7, DECK), (-11.6, DECK), (-13.3, 9.6), (-13.7, 9.6)]
    va = [ab.bm.verts.new((x, y0, z)) for x, z in pts]
    vb = [ab.bm.verts.new((x, y1, z)) for x, z in pts]
    ab.bm.faces.new(list(reversed(va)))
    ab.bm.faces.new(vb)
    for i in range(4):
        j = (i + 1) % 4
        ab.bm.faces.new((va[i], va[j], vb[j], vb[i]))
    ab.tag([f for f in ab.bm.faces if f not in before], "White")
ab.cylinder((-13.55, -7.3, L1 + 1.2), (-13.55, 7.3, L1 + 1.2), 0.03, None, 6, "Rail", caps=False)
ab.cylinder((-13.55, -7.3, L1 + 0.68), (-13.55, 7.3, L1 + 0.68), 0.022, None, 6, "Rail", caps=False)
for k in range(10):
    y = -7.3 + k * 14.6 / 9
    ab.cylinder((-13.55, y, L1 + 0.15), (-13.55, y, L1 + 1.2), 0.026, None, 6, "Rail", caps=False)
ab.finish(smooth_angle=30)

# aft end of the superstructure (photo): a low solid bulwark round the blue aft roof deck, and a
# white side plate closing the back of each boat bay, its forward edge raked down and forward
aw = Builder("AftSuperstructure")
for s in (1, -1):
    aw.box(-13.75, -8.8, min(s * (SW - 0.08), s * (SW + 0.02)), max(s * (SW - 0.08), s * (SW + 0.02)), L1, L1 + 0.55, "White")
aw.box(-13.8, -13.7, -SW, SW, L1, L1 + 0.55, "White")
for s in (1, -1):
    y0, y1 = sorted((s * SW, s * (SW + 0.06)))
    before = set(aw.bm.faces)
    pts = [(-13.75, DECK), (-10.9, DECK), (-12.7, 9.6), (-13.75, 9.6)]
    va = [aw.bm.verts.new((x, y0, z)) for x, z in pts]
    vb = [aw.bm.verts.new((x, y1, z)) for x, z in pts]
    aw.bm.faces.new(list(reversed(va)))
    aw.bm.faces.new(vb)
    for i in range(4):
        j = (i + 1) % 4
        aw.bm.faces.new((va[i], va[j], vb[j], vb[i]))
    aw.tag([f for f in aw.bm.faces if f not in before], "White")
    # life-buoy on the plate
    aw.cylinder((-12.9, s * (SW + 0.06), 8.2), (-12.9, s * (SW + 0.14), 8.2), 0.34, None, 18, "Orange")
aw.finish(smooth_angle=30)

misc = Builder("Fittings")
# blue roof decks
misc.box(-13.5, -9.0, -SW + 0.25, SW - 0.25, L1, L1 + 0.15, "BlueDeck")
misc.box(6.6, 8.6, -SW + 0.4, SW - 0.4, L1, L1 + 0.15, "BlueDeck")
# side windows (slightly proud dark panes)
for s in (1, -1):
    yy = s * (SW + 0.02)
    for x in (7.0,):
        misc.box(x, x + 1.2, yy - 0.03, yy + 0.03, 10.0, 10.8, "Glass")
# white boxes under the bridge, one each side, carrying the Bagong Pilipinas logo (photos)
for s in (1, -1):
    misc.box(0.4, 2.4, min(s * SW, s * (SW + 0.35)), max(s * SW, s * (SW + 0.35)), 9.9, L1, "White")
    misc.box(0.3, 2.5, min(s * SW, s * (SW + 0.42)), max(s * SW, s * (SW + 0.42)), L1 - 0.08, L1 + 0.02, "White")
# hangar door with ribs
misc.box(-13.85, -13.72, -2.5, 2.5, 7.3, 10.8, "HangarDoor")
for k in range(9):
    z = 7.7 + k * 0.38
    misc.box(-13.9, -13.84, -2.4, 2.4, z, z + 0.05, "Steel")
# boat bays: dark inner walls and bay roof underside
for s in (1, -1):
    misc.box(-13.3, 1.8, min(s * 3.11, s * 3.16), max(s * 3.11, s * 3.16), DECK, 9.6, "Dark")           # inner wall, on the hangar side face
    misc.box(-13.3, 1.8, min(s * 3.12, s * (SW - 0.05)), max(s * 3.12, s * (SW - 0.05)), DECK + 0.32, DECK + 0.36, "Dark")  # bay floor
    misc.box(-13.3, 1.8, min(s * 3.1, s * SW), max(s * 3.1, s * SW), 9.55, 9.6, "Dark")
# helideck plate and aft working deck
misc.box(-33.1, -13.8, -7.6, 7.6, DECK + 0.25, DECK + 0.55, "Helideck",
         uvfn=lambda co, f: ((co.x + 33.1) / 19.3, (co.y + 7.6) / 15.2))
misc.box(-41.8, -33.1, -7.5, 7.5, DECK + 0.02, DECK + 0.2, "BlueDeck")
# deck crane (orange) and life-raft canisters at the stern
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
    rail_run([(6.6, s * 7.3), (8.6, s * 7.3)], L1 + 0.15)
rail_run([(-41.6, 7.5), (-41.6, -7.5)], DECK + 0.2)
rail_run([(9.0, 7.2), (14.0, 7.2), (15.4, 6.7), (16.1, 5.4), (16.25, 0.0), (16.1, -5.4), (15.4, -6.7), (14.0, -7.2), (9.0, -7.2)], L1 + 0.15)   # forward deckhouse roof
rails.finish()

# ------------------------------------------------------------------ mast (from photos): enclosed tapered tower, yards, forward radar arm; radars separate
MX = -2.7
base_z, top_z = L2 + 0.1, 22.0


def octagon(hx, hy):
    c = 0.28 * min(hx, hy)
    return [(hx, -hy + c), (hx, hy - c), (hx - c, hy), (-hx + c, hy), (-hx, hy - c), (-hx, -hy + c), (-hx + c, -hy), (hx - c, -hy)]


def mast_half(z):
    t = (z - base_z) / (top_z - base_z)
    return 0.85 - 0.35 * t, 0.75 - 0.3 * t


mast = Builder("Mast")
prism(mast, [(MX + x, y) for x, y in octagon(*mast_half(base_z))], [(MX + x, y) for x, y in octagon(*mast_half(top_z))],
      base_z, top_z, "Mast", caps=(False, True))
# dark panels on the forward face, rung ladder up the aft face
for z0, z1 in ((15.2, 16.2), (17.0, 17.6)):
    hx, _ = mast_half((z0 + z1) / 2)
    mast.box(MX + hx - 0.01, MX + hx + 0.04, -0.28, 0.28, z0, z1, "Glass")
for k in range(int((top_z - base_z - 0.4) / 0.35)):
    z = base_z + 0.3 + k * 0.35
    hx, _ = mast_half(z)
    mast.box(MX - hx - 0.12, MX - hx - 0.02, -0.22, 0.22, z, z + 0.03, "Mast")
# lower yard with navigation lights and whips, upper cross-arm with short antennas
mast.box(MX - 0.08, MX + 0.08, -2.6, 2.6, 18.3, 18.45, "Mast")
mast.box(MX - 0.06, MX + 0.06, -1.7, 1.7, 21.0, 21.12, "Mast")
for s in (1, -1):
    mast.box(MX - 0.12, MX + 0.12, s * 2.5 - 0.12, s * 2.5 + 0.12, 18.45, 18.7, "Dark")
    mast.cylinder((MX, s * 2.2, 18.45), (MX, s * 2.2, 20.4), 0.03, 0.012, 6, "Mast")
    for y in (0.9, 1.5):
        mast.cylinder((MX, s * y, 21.12), (MX, s * y, 21.9), 0.025, 0.012, 6, "Mast")
    mast.cylinder((MX, s * 2.6, 18.3), (MX - 3.0, s * 4.4, L2 + 0.2), 0.008, None, 3, "Dark", caps=False, smooth=False)
# forward arm carrying the S-band radar, with a diagonal strut
mast.box(MX + 0.4, MX + 3.4, -0.45, 0.45, 19.25, 19.4, "Mast")
mast.cylinder((MX + 0.6, 0, 18.0), (MX + 3.0, 0, 19.25), 0.08, 0.06, 8, "Mast")
# top platform for the X-band radar; aft bracket with the all-round light
mast.box(MX - 0.75, MX + 0.75, -0.7, 0.7, top_z, top_z + 0.12, "Mast")
mast.cylinder((MX - 0.45, 0, top_z - 0.3), (MX - 1.9, 0, top_z + 0.3), 0.06, 0.05, 8, "Mast")
mast.cylinder((MX - 1.9, 0, top_z + 0.3), (MX - 1.9, 0, top_z + 2.0), 0.05, 0.03, 8, "Mast")
mast.cylinder((MX - 1.9, 0, top_z + 2.0), (MX - 1.9, 0, top_z + 2.2), 0.08, None, 10, "Lamp")
mast.cylinder((MX - 1.9, 0, top_z + 2.2), (MX - 1.9, 0, top_z + 2.9), 0.012, 0.006, 4, "Mast")
# EO/IR camera on a bracket at the front, horn beside it
mast.cylinder((MX + 0.7, 0, 16.9), (MX + 1.2, 0, 16.9), 0.05, None, 8, "Mast")
mast.sphere((MX + 1.3, 0, 16.75), 0.26, "Dark", 16, 10)
mast.cylinder((MX + 0.7, 0.4, 17.9), (MX + 1.2, 0.4, 17.9), 0.07, 0.18, 12, "Dark")
mast.finish(smooth_angle=30)


def radar(name, z, length, size, x=MX):
    """Open-array radar antenna: slim array with a dark radiating face, end caps, support
    arms and a gearbox on the pedestal; origin on the rotation axis."""
    b = Builder(name)
    h = size * 1.3
    b.box(-size * 0.45, size * 0.45, -length / 2, length / 2, -h * 0.25, h * 0.75, "Radar")
    b.box(size * 0.45, size * 0.5, -length / 2 + 0.1, length / 2 - 0.1, -h * 0.15, h * 0.65, "Dark")
    for sgn in (1, -1):
        b.box(-size * 0.5, size * 0.5, sgn * length / 2 - 0.06, sgn * length / 2 + 0.06, -h * 0.3, h * 0.8, "Radar")
        b.cylinder((0, 0, -h * 0.25), (0, sgn * length * 0.3, -h * 0.25 + 0.02), 0.05, None, 6, "Steel")
    b.box(-size * 0.9, size * 0.9, -size * 0.9, size * 0.9, -h * 1.5, -h * 0.4, "Radar")
    b.cylinder((0, 0, -h * 0.4), (0, 0, -h * 0.25), size * 0.35, None, 12, "Steel")
    ob = b.finish(bevel=0.02)
    ob.location = (x, 0, z)
    return ob


radar("RadarS", 19.75, 4.0, 0.26, MX + 2.7)   # SharpEye S-band on the forward arm
radar("RadarX", top_z + 0.55, 2.6, 0.2)       # SharpEye X-band on top

# ------------------------------------------------------------------ boat bays: 9.5 m RHIBs on davits
# Deep-V GRP hull, inflatable collar with a rubbing strake, non-slip deck, centre console with
# windscreen, jockey seats, A-frame with light/radar/whip, inboard waterjet; keel chocks and the
# davit's single-point lift hook with four slings.
RHIB_L = 9.5


def rhib_half_beam(t):
    """Hull half beam at t = 0 (transom) .. 1 (bow)."""
    if t < 0.6:
        return 1.2
    return max(0.04, 1.2 * (1 - ((t - 0.6) / 0.4) ** 2.1))


for s, nm in ((1, "RhibPort"), (-1, "RhibStarboard")):
    r = Builder(nm)
    y0 = s * 5.2
    xs = -RHIB_L / 2
    z0 = DECK + 0.62                                    # keel at the transom
    N = 20
    rings, tops = [], []
    for i in range(N + 1):
        t = i / N
        x = xs + RHIB_L * t
        w = rhib_half_beam(t)
        keel = z0 + max(0.0, (t - 0.68) / 0.32) ** 2 * 0.6
        top = z0 + 0.78 + max(0.0, (t - 0.72) / 0.28) ** 2 * 0.25
        half = [(0.0, keel), (w * 0.5, min(top, keel + 0.2)), (w * 0.9, min(top, keel + 0.42)), (w, top)]
        ring = [(-yy, zz) for yy, zz in reversed(half[1:])] + half
        rings.append([r.bm.verts.new((x, y0 + yy, zz)) for yy, zz in ring])
        tops.append((x, w, top))
    hull_faces = []
    for i in range(N):
        a, c = rings[i], rings[i + 1]
        for k in range(len(a) - 1):
            hull_faces.append(r.bm.faces.new((a[k], a[k + 1], c[k + 1], c[k])))
    hull_faces.append(r.bm.faces.new(list(rings[0])))
    r.tag(hull_faces, "White", smooth=True)
    # collar: tube segments along both gunwales, meeting at the bow, coned ends past the transom
    R = 0.4
    for side in (1, -1):
        pts = [(x, y0 + side * (w + R * 0.35), top + R * 0.6) for x, w, top in tops]
        pts[-1] = (tops[-1][0] + 0.25, y0, tops[-1][2] + R * 0.6)
        pts = pts[:-1:2] + [pts[-1]]                  # fewer, slightly overlapping segments
        for (xa, ya, za), (xb, yb, zb) in zip(pts, pts[1:]):
            d = Vector((xb - xa, yb - ya, zb - za)).normalized() * 0.06
            r.cylinder((xa - d.x, ya - d.y, za - d.z), (xb + d.x, yb + d.y, zb + d.z), R, None, 12, "RhibGrey", caps=False)
        x0_, y0_, z0_ = pts[0]
        r.cylinder((x0_, y0_, z0_), (x0_ - 0.55, y0_, z0_ + 0.02), R, R * 0.55, 12, "RhibGrey")
        # rubbing strake low on the outboard side of the tube
        for (xa, ya, za), (xb, yb, zb) in zip(pts[:-3], pts[1:-2]):
            r.cylinder((xa, ya + side * R * 0.75, za - R * 0.45), (xb, yb + side * R * 0.75, zb - R * 0.45), 0.07, None, 6, "Rubber", caps=False)
    # deck
    dz = z0 + 0.5
    deck_pts = [(x, w * 0.92) for x, w, _ in tops[1:-4]]
    prev = None
    for x, w in deck_pts:
        pair = (r.bm.verts.new((x, y0 - w, dz)), r.bm.verts.new((x, y0 + w, dz)))
        if prev:
            r.tag([r.bm.faces.new((prev[0], pair[0], pair[1], prev[1]))], "Rubber")
        prev = pair
    # centre console, windscreen and grab rail
    r.box(0.2, 1.4, y0 - 0.55, y0 + 0.55, dz, dz + 0.85, "White")
    r.box(1.35, 1.45, y0 - 0.5, y0 + 0.5, dz + 0.85, dz + 1.25, "Glass")
    r.box(0.2, 1.4, y0 - 0.58, y0 + 0.58, dz + 0.85, dz + 0.9, "White")
    for sgn in (1, -1):
        r.cylinder((0.1, y0 + sgn * 0.62, dz + 0.4), (0.1, y0 + sgn * 0.62, dz + 1.0), 0.03, None, 6, "Rail")
        r.cylinder((1.45, y0 + sgn * 0.62, dz + 0.4), (1.45, y0 + sgn * 0.62, dz + 1.3), 0.03, None, 6, "Rail")
        r.cylinder((0.1, y0 + sgn * 0.62, dz + 1.0), (1.45, y0 + sgn * 0.62, dz + 1.3), 0.03, None, 6, "Rail")
    # jockey seats and the rear bench
    for x in (-0.6, -1.5):
        r.box(x - 0.25, x + 0.25, y0 - 0.25, y0 + 0.25, dz, dz + 0.55, "Steel")
        r.box(x - 0.4, x + 0.4, y0 - 0.32, y0 + 0.32, dz + 0.55, dz + 0.85, "Rubber")
    r.box(-3.0, -2.4, y0 - 0.85, y0 + 0.85, dz, dz + 0.5, "Rubber")
    # A-frame (low enough to stow under the bay roof) with the navigation light, radar, and the whip folded aft
    for sgn in (1, -1):
        r.cylinder((-3.3, y0 + sgn * 0.95, dz), (-3.05, y0 + sgn * 0.75, dz + 1.15), 0.05, None, 8, "Rail")
    r.cylinder((-3.05, y0 - 0.75, dz + 1.15), (-3.05, y0 + 0.75, dz + 1.15), 0.05, None, 8, "Rail")
    r.cylinder((-3.0, y0, dz + 1.15), (-3.0, y0, dz + 1.32), 0.22, 0.2, 14, "Radar")
    r.cylinder((-3.05, y0 + 0.4, dz + 1.15), (-3.05, y0 + 0.4, dz + 1.3), 0.05, None, 8, "Lamp")
    r.cylinder((-3.05, y0 - 0.5, dz + 1.2), (-4.4, y0 - 0.5, dz + 1.25), 0.02, 0.008, 4, "Mast")
    # inboard engine cover and waterjet at the transom
    r.box(-4.5, -3.5, y0 - 0.6, y0 + 0.6, dz, dz + 0.55, "White")
    r.cylinder((xs + 0.02, y0, z0 + 0.25), (xs - 0.35, y0, z0 + 0.25), 0.18, 0.14, 12, "Steel")
    # bow towing eye
    r.cylinder((tops[-1][0] - 0.15, y0, tops[-1][2] - 0.25), (tops[-1][0] + 0.12, y0, tops[-1][2] - 0.35), 0.04, None, 6, "Steel")
    # keel chocks on the bay floor
    for x in (-3.0, 0.0, 2.6):
        r.box(x - 0.2, x + 0.2, y0 - 0.7, y0 + 0.7, DECK, z0 + 0.1, "Steel")
    # davit: post, arm, hook block and four slings to the boat's lifting points
    r.cylinder((-0.5, s * 3.3, DECK), (-0.5, s * 3.3, 9.3), 0.18, 0.14, 12, "Rail")
    r.cylinder((-0.5, s * 3.3, 9.3), (-0.5, y0, 9.45), 0.14, None, 12, "Rail")
    r.box(-0.62, -0.38, y0 - 0.12, y0 + 0.12, 9.05, 9.35, "Orange")
    for x, dy in ((-2.6, 0.75), (-2.6, -0.75), (1.8, 0.75), (1.8, -0.75)):
        r.cylinder((-0.5, y0, 9.05), (x, y0 + dy, dz + 0.1), 0.012, None, 4, "Dark", caps=False, smooth=False)
    r.finish(smooth_angle=40)

# ------------------------------------------------------------------ finer detail (things that read at landing-page distance)
det = Builder("Details")
# watertight doors and portholes on the superstructure sides
for s in (1, -1):
    for x0, yy in ((2.5, SW), (9.3, SW - 0.2)):
        y = s * (yy + 0.02)
        det.box(x0, x0 + 0.9, y - 0.04, y + 0.04, DECK + 0.2, DECK + 2.1, "Steel")
        det.box(x0 + 0.65, x0 + 0.78, y + s * 0.03, y + s * 0.09, DECK + 1.0, DECK + 1.15, "Dark")
# helideck: perimeter safety nets outboard of the edge, landing lights
for s in (1, -1):
    det.box(-33.0, -14.0, min(s * 7.6, s * 8.6), max(s * 7.6, s * 8.6), DECK + 0.42, DECK + 0.48, "Net")
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
    x = 33.0
    y = s * (half_beam(x) * 0.99 + 0.08)
    det.box(x - 0.12, x + 0.12, y - 0.08, y + 0.08, 5.15, 6.75, "Dark")
    det.box(x - 0.55, x + 0.55, y - 0.1, y + 0.1, 4.75, 5.2, "Dark")
    det.cylinder((x - 0.55, y, 5.0), (x - 0.75, y, 5.5), 0.11, 0.05, 8, "Dark")
    det.cylinder((x + 0.55, y, 5.0), (x + 0.75, y, 5.5), 0.11, 0.05, 8, "Dark")
# rubbing strakes along the parallel body
for s in (1, -1):
    det.cylinder((-33.0, s * 8.04, 5.9), (4.0, s * 8.04, 5.9), 0.1, None, 8, "White")
    det.cylinder((-33.0, s * 8.03, 2.4), (-6.0, s * 8.03, 2.4), 0.08, None, 8, "White")
# forecastle (from the bow photo): a solid bulwark, flush with the hull side and painted with it,
# runs from the deckhouse to the stem and rises into a high bow shield; railing on its aft part;
# blue deck out to the bulwark
FB_T = 0.16
FB_X0 = 18.6
fd = Builder("ForeDeck")
prev = None
dz = DECK + 0.34
for i in range(41):
    x = FB_X0 + (39.5 - FB_X0) * i / 40
    w = max(0.05, half_beam(x) - FB_T - 0.02)
    pair = (fd.bm.verts.new((x, w, dz)), fd.bm.verts.new((x, -w, dz)))
    if prev:
        fd.tag([fd.bm.faces.new((prev[0], pair[0], pair[1], prev[1]))], "BlueDeck")
    prev = pair
fd.finish()

# railing on top of the bulwark, aft part
for s in (1, -1):
    pts = [(FB_X0 + 0.2 + (31.0 - FB_X0 - 0.2) * i / 8) for i in range(9)]
    for xa, xb in zip(pts, pts[1:]):
        ya, yb = s * (half_beam(xa) - FB_T / 2), s * (half_beam(xb) - FB_T / 2)
        za, zb = deck_z(xa) + bulwark_h(xa), deck_z(xb) + bulwark_h(xb)
        det.cylinder((xa, ya, za + 0.75), (xb, yb, zb + 0.75), 0.035, None, 6, "Rail", caps=False)
        det.cylinder((xa, ya, za), (xa, ya, za + 0.75), 0.03, None, 6, "Rail", caps=False)
# white fittings on the forecastle seen in the photo: a dome-topped locker and small boxes
det.cylinder((26.5, 0.0, dz), (26.5, 0.0, dz + 0.9), 0.75, 0.75, 20, "White")
det.sphere((26.5, 0.0, dz + 0.9), 0.75, "White", 20, 10)
for y in (-2.2, 2.2):
    det.box(22.6, 23.6, y - 0.4, y + 0.4, dz, dz + 0.7, "White")
det.profile([(19.2, DECK + 0.3), (20.4, DECK + 0.3), (19.6, DECK + 1.3)], 4.6, "White")
# boarding ladder rungs on the hull side under the bays
for s in (1, -1):
    for k in range(8):
        z = 1.2 + k * 0.6
        det.box(-6.4, -5.6, s * 8.02 - 0.03, s * 8.02 + 0.03, z, z + 0.06, "Steel")
det.finish(smooth_angle=35)

# ------------------------------------------------------------------ stern and helideck detail
st = Builder("SternDetails")
HD = DECK + 0.55            # helideck surface
# helideck: raised edge coaming with a drain gutter, green perimeter lights all round
for s in (1, -1):
    st.box(-33.1, -13.8, min(s * 7.45, s * 7.6), max(s * 7.45, s * 7.6), HD - 0.02, HD + 0.06, "Steel")
    for k in range(9):
        x = -32.6 + k * 2.3
        st.cylinder((x, s * 7.3, HD), (x, s * 7.3, HD + 0.12), 0.1, 0.09, 10, "Green")
for k in range(6):
    y = -6.0 + k * 2.4
    st.cylinder((-32.95, y, HD), (-32.95, y, HD + 0.12), 0.1, 0.09, 10, "Green")
st.box(-33.25, -33.05, -7.6, 7.6, HD - 0.02, HD + 0.06, "Steel")
# hangar front: door frame, wing doors, control-room window, floodlights, foam monitors
st.box(-13.95, -13.7, -2.75, -2.5, DECK + 0.55, 11.0, "White")
st.box(-13.95, -13.7, 2.5, 2.75, DECK + 0.55, 11.0, "White")
st.box(-13.95, -13.7, -2.75, 2.75, 10.8, 11.05, "White")
for s in (1, -1):
    st.box(-13.82, -13.72, s * 4.2 - 0.5, s * 4.2 + 0.5, HD, HD + 2.0, "Steel")            # personnel door
    st.box(-13.84, -13.8, s * 4.2 + 0.25 * s - 0.06, s * 4.2 + 0.25 * s + 0.06, HD + 0.9, HD + 1.05, "Dark")
    st.box(-13.8, -13.72, s * 5.6 - 0.6, s * 5.6 + 0.6, 10.1, 10.9, "Glass")                # flight-control window
    for z in (10.95, 9.3):
        st.box(-14.15, -13.75, s * 3.4 - 0.25, s * 3.4 + 0.25, z, z + 0.3, "Dark")          # floodlights
        st.box(-14.17, -14.13, s * 3.4 - 0.2, s * 3.4 + 0.2, z + 0.05, z + 0.25, "Lamp")
    # foam monitors at the helideck corners
    st.cylinder((-14.6, s * 6.6, HD), (-14.6, s * 6.6, HD + 0.9), 0.18, 0.15, 12, "Red")
    st.sphere((-14.6, s * 6.6, HD + 1.0), 0.22, "Red", 12, 8)
    st.cylinder((-14.6, s * 6.6, HD + 1.05), (-15.6, s * 6.3, HD + 1.3), 0.08, 0.05, 8, "Red")
# windsock on the hangar roof corner: mast and an orange/white striped cone streaming aft
ws_x, ws_y, ws_z = -13.3, -7.2, L1 + 0.15
st.cylinder((ws_x, ws_y, ws_z), (ws_x, ws_y, ws_z + 4.0), 0.05, 0.04, 8, "Mast")
for k in range(5):
    xa = ws_x - 0.1 - k * 0.32
    ra = 0.32 - k * 0.04
    st.cylinder((xa, ws_y, ws_z + 3.9 - k * 0.05), (xa - 0.32, ws_y, ws_z + 3.9 - (k + 1) * 0.05), ra, ra - 0.04, 14,
                "Orange" if k % 2 == 0 else "White", caps=(k == 4), smooth=True)
# deck crane: pedestal, slewing house, knuckle boom with hydraulic ram, hook block
cx, cy = -37.6, -3.6
st.box(cx - 0.7, cx + 0.7, cy - 0.7, cy + 0.7, DECK + 0.2, DECK + 0.5, "Steel")
st.cylinder((cx, cy, DECK + 0.5), (cx, cy, DECK + 1.9), 0.42, 0.42, 18, "Orange")
st.box(cx - 0.55, cx + 0.55, cy - 0.5, cy + 0.5, DECK + 1.9, DECK + 2.6, "Orange")
st.box(cx - 0.75, cx - 0.5, cy - 0.35, cy + 0.35, DECK + 2.0, DECK + 2.5, "Dark")        # operator panel
st.cylinder((cx + 0.2, cy, DECK + 2.4), (cx + 3.4, cy, DECK + 4.0), 0.2, 0.15, 10, "Orange")  # main boom
st.cylinder((cx + 3.4, cy, DECK + 4.0), (cx + 5.0, cy, DECK + 3.0), 0.14, 0.1, 10, "Orange")  # jib (knuckle)
st.cylinder((cx + 0.3, cy, DECK + 1.7), (cx + 1.9, cy, DECK + 3.05), 0.1, None, 8, "Steel")   # ram
st.cylinder((cx + 5.0, cy, DECK + 3.0), (cx + 5.0, cy, DECK + 1.5), 0.015, None, 4, "Dark")   # wire
st.box(cx + 4.85, cx + 5.15, cy - 0.12, cy + 0.12, DECK + 1.2, DECK + 1.5, "Orange")         # hook block
# life-raft canister cradles
for y in (-5.2, -3.8, 5.5, 4.1):
    for x in (-40.3, -39.1):
        st.box(x - 0.08, x + 0.08, y - 0.55, y + 0.55, DECK + 0.4, DECK + 0.75, "Steel")
# aft deck hatches, mooring winches, stern fairleads, lights and an ensign staff
st.box(-37.0, -35.4, 2.0, 3.6, DECK + 0.2, DECK + 0.3, "Steel")
st.box(-34.5, -33.6, -1.0, 1.0, DECK + 0.2, DECK + 0.3, "Steel")
for s in (1, -1):
    st.cylinder((-36.2, s * 4.7, DECK + 0.6), (-36.2, s * 4.7 + s * 1.0, DECK + 0.6), 0.4, None, 16, "Dark")
    st.box(-36.6, -35.8, s * 4.5 - 0.2, s * 4.5 + 0.2, DECK + 0.2, DECK + 0.6, "Steel")
    st.box(-42.05, -41.85, s * 3.6 - 0.45, s * 3.6 + 0.45, DECK - 0.55, DECK - 0.15, "Dark")   # transom fairleads
    st.box(-42.08, -41.9, s * 6.2 - 0.18, s * 6.2 + 0.18, DECK - 1.1, DECK - 0.9, "Lamp")     # stern lights
st.cylinder((-41.5, 0.0, DECK + 0.2), (-41.5, 0.0, DECK + 3.4), 0.05, 0.035, 8, "Mast")
st.sphere((-41.5, 0.0, DECK + 3.45), 0.08, "Mast", 8, 6)
st.finish(smooth_angle=35)

# ------------------------------------------------------------------ superstructure detail
sd = Builder("SuperDetails")
# drip ledges along the roof edges (they read as crisp shadow lines on the white walls)
for s in (1, -1):
    sd.box(-13.7, 8.85, min(s * SW, s * (SW + 0.1)), max(s * SW, s * (SW + 0.1)), L1 - 0.12, L1 - 0.02, "White")
    sd.box(8.8, 14.0, min(s * (SW - 0.2), s * (SW - 0.1)), max(s * (SW - 0.2), s * (SW - 0.1)), L1 - 0.12, L1 - 0.02, "White")
sd.box(8.8, 8.9, -SW, SW, L1 - 0.12, L1 - 0.02, "White")
# railing round the bridge roof, following its rounded plan
rp = br_plan(L2, -0.15, 3)
for (xa, ya), (xb, yb) in zip(rp, rp[1:] + rp[:1]):
    sd.cylinder((xa, ya, L2 + 1.15), (xb, yb, L2 + 1.15), 0.03, None, 6, "Rail", caps=False)
    sd.cylinder((xa, ya, L2 + 0.65), (xb, yb, L2 + 0.65), 0.022, None, 6, "Rail", caps=False)
    n = max(1, int(math.hypot(xb - xa, yb - ya) / 1.6))
    for i in range(n):
        t = i / n
        px_, py_ = xa + (xb - xa) * t, ya + (yb - ya) * t
        sd.cylinder((px_, py_, L2 + 0.15), (px_, py_, L2 + 1.15), 0.026, None, 6, "Rail", caps=False)


# inclined ladders: 01 roof up to the forward roof, and aft roof up to the bridge roof
def ladder(p0, p1, width=0.7, steps=8):
    p0, p1 = Vector(p0), Vector(p1)
    side = Vector((0, width / 2, 0))
    for sgn in (1, -1):
        sd.cylinder(p0 + side * sgn, p1 + side * sgn, 0.04, None, 6, "Rail", caps=False)
        sd.cylinder(p0 + side * sgn + Vector((0, 0, 0.9)), p1 + side * sgn + Vector((0, 0, 0.9)), 0.025, None, 6, "Rail", caps=False)
    for i in range(1, steps):
        q = p0.lerp(p1, i / steps)
        sd.box(q.x - 0.1, q.x + 0.1, q.y - width / 2, q.y + width / 2, q.z - 0.02, q.z + 0.02, "Steel")


for s in (1, -1):
    ladder((-11.0, s * 1.8, L1 + 0.15), (-8.9, s * 1.8, L2 + 0.15))
# roof equipment: air-conditioning condensers, mushroom vents, cable trays
for x, y in ((7.4, 3.8), (7.4, -3.8)):
    sd.box(x - 0.6, x + 0.6, y - 0.45, y + 0.45, L1 + 0.15, L1 + 0.95, "White")
    sd.cylinder((x, y, L1 + 0.95), (x, y, L1 + 1.0), 0.36, None, 16, "Dark")
for x, y in ((-6.0, 3.2), (-6.0, -3.2), (2.0, 0.0)):
    z0 = L1 + 0.15 if x > 9 else L2 + 0.15
    sd.cylinder((x, y, z0), (x, y, z0 + 0.6), 0.14, None, 10, "White")
    sd.cylinder((x, y, z0 + 0.6), (x, y, z0 + 0.72), 0.3, 0.22, 14, "White")
for s in (1, -1):
    sd.box(-8.2, 3.8, s * 5.6 - 0.15, s * 5.6 + 0.15, L2 + 0.15, L2 + 0.27, "Steel")
# bridge roof: horn, EO camera, magnetic compass, VHF whips
sd.cylinder((4.6, 1.8, L2 + 0.15), (4.6, 1.8, L2 + 1.2), 0.08, None, 8, "Mast")
sd.cylinder((4.6, 1.8, L2 + 1.2), (5.2, 1.8, L2 + 1.25), 0.06, 0.18, 12, "Mast")
sd.cylinder((5.6, 0.0, L2 + 0.15), (5.6, 0.0, L2 + 1.1), 0.18, 0.14, 12, "White")
sd.sphere((5.6, 0.0, L2 + 1.25), 0.22, "Glass", 14, 8)
sd.cylinder((0.0, 2.0, L2 + 0.15), (0.0, 2.0, L2 + 1.3), 0.12, None, 10, "White")
sd.sphere((0.0, 2.0, L2 + 1.55), 0.32, "Dark", 16, 10)
for x, y in ((-7.6, 5.4), (-7.6, -5.4), (4.4, -4.4), (4.4, 4.4)):
    sd.cylinder((x, y, L2 + 0.15), (x, y, L2 + 3.2), 0.035, 0.015, 6, "Mast")
sd.finish(smooth_angle=35)


# ------------------------------------------------------------------ gaff for the flag (the flag itself is cloth in the web scene)
gaff = Builder("Gaff")
gaff.cylinder((MX - 0.5, 0, 20.3), (MX - 4.0, 0, 21.8), 0.08, 0.05, 8, "Mast")
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
