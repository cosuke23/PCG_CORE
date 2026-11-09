"""Companion files for the walkthrough deck:
  - ROAP_Screenshot_Evidence/annotated/*.png  (flattened copies with the same annotations)
  - ROAP_Workflow_Inventory.xlsx               (routes, roles, verified workflows, coverage)
  - ROAP_QA_Validation.xlsx                    (slide-by-slide validation matrix)
  - ROAP_Screenshot_Evidence/manifest.csv      (file, URL, role, time, viewport, step)

usage: python3 build_docs.py OUT_DIR ROUTES_JSON PCG_CORE_DIR
Run after capture.js and build_deck.js (it reads steps.json and deck_steps.json).
"""
import csv, json, os, re, sys
from PIL import Image, ImageDraw, ImageFont
from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

OUT, ROUTES, APP = sys.argv[1], sys.argv[2], sys.argv[3]
EV = os.path.join(OUT, 'ROAP_Screenshot_Evidence')
data = json.load(open(os.path.join(OUT, 'steps.json')))
deck = {d['id']: d for d in json.load(open(os.path.join(OUT, 'deck_steps.json')))}
steps = sorted(data['steps'], key=lambda s: (s['device'] != 'desktop', s['id']))
for s in steps:
    d = deck.get(s['id'], {})
    s['slide'] = d.get('slide')
    s['n'], s['total'] = d.get('n'), d.get('total')
    s['shown'] = d.get('zoomedFile') or ('original/' + s['file'])
    s['deck_boxes'], s['deck_image'], s['crop'] = d.get('boxes', s['boxes']), d.get('image', s['image']), d.get('crop')

# ------------------------------------------------------------ annotated copies
COL = {'click': (0, 180, 216), 'field': (37, 99, 235), 'result': (22, 163, 74), 'caution': (245, 158, 11), 'error': (220, 38, 38)}
os.makedirs(os.path.join(EV, 'annotated'), exist_ok=True)
try:
    font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 22)
except OSError:
    font = ImageFont.load_default()
for s in steps:
    src = os.path.join(EV, s['shown'])
    im = Image.open(src).convert('RGB')
    sc = im.width / s['deck_image']['w']  # phone captures are 2x pixels per CSS px
    d = ImageDraw.Draw(im)
    for i, b in enumerate(s['deck_boxes']):
        c = COL.get(b['kind'], COL['click'])
        x0, y0, x1, y1 = [v * sc for v in (b['x'] - 4, b['y'] - 4, b['x'] + b['w'] + 4, b['y'] + b['h'] + 4)]
        if b['kind'] == 'field':
            for xx in range(int(x0), int(x1), 18):
                d.line([(xx, y0), (min(xx + 10, x1), y0)], fill=c, width=4); d.line([(xx, y1), (min(xx + 10, x1), y1)], fill=c, width=4)
            for yy in range(int(y0), int(y1), 18):
                d.line([(x0, yy), (x0, min(yy + 10, y1))], fill=c, width=4); d.line([(x1, yy), (x1, min(yy + 10, y1))], fill=c, width=4)
        else:
            d.rectangle([x0, y0, x1, y1], outline=c, width=4)
        r = 16 * sc ** 0.5
        cx, cy = max(r, x0 - r - 4), max(r, (y0 + y1) / 2)
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(11, 22, 38), outline=c, width=3)
        d.text((cx, cy), str(i + 1), fill='white', font=font, anchor='mm')
    im.save(os.path.join(EV, 'annotated', s['id'] + '.png'))

with open(os.path.join(EV, 'manifest.csv'), 'w', newline='') as f:
    w = csv.writer(f)
    w.writerow(['step_id', 'slide', 'section', 'workflow', 'step', 'role', 'route', 'viewport', 'device', 'captured_utc', 'original', 'shown_on_slide', 'annotated', 'title'])
    for s in steps:
        w.writerow([s['id'], s['slide'], s['section'], s['workflow'], f"{s['n']} of {s['total']}" if s['n'] else '', s['role'], s['url'], s['viewport'], s['device'],
                    s['time'], 'original/' + s['file'], s['shown'], 'annotated/' + s['id'] + '.png', s['title']])

# ------------------------------------------------------------ helpers
HEAD = PatternFill('solid', fgColor='0B1626')
OKF, BADF, WARNF = PatternFill('solid', fgColor='DCFCE7'), PatternFill('solid', fgColor='FEE2E2'), PatternFill('solid', fgColor='FEF3C7')

def sheet(wb, title, header, rows, widths):
    ws = wb.create_sheet(title)
    ws.append(header)
    for c in ws[1]:
        c.font, c.fill = Font(bold=True, color='FFFFFF'), HEAD
        c.alignment = Alignment(vertical='center', wrap_text=True)
    for r in rows:
        ws.append(r)
    for i, wd in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(i)].width = wd
    for row in ws.iter_rows(min_row=2):
        for c in row:
            c.alignment = Alignment(vertical='top', wrap_text=True)
    ws.freeze_panes = 'A2'
    ws.auto_filter.ref = ws.dimensions
    return ws

def color_status(ws, col):
    for row in ws.iter_rows(min_row=2):
        c = row[col - 1]
        v = str(c.value or '')
        c.fill = OKF if v.startswith(('Verified', 'Passed', 'PASS')) else BADF if v.startswith(('NOT IMPLEMENTED', 'Failed', 'FAIL')) else WARNF if v else c.fill

# ------------------------------------------------------------ inventory
routes = json.load(open(ROUTES))
def audience(mw, uri):
    m = ' '.join(mw)
    if 'EnsureAdminSession' in m: return 'Administrator'
    if 'EnsureUnitSession' in m: return 'Unit checker' + (' (M1)' if 'EnsureUnitM1' in m else '')
    if 'Authenticate' in m: return 'Applicant / member'
    if uri.startswith('admin/two-factor'): return 'Administrator (sign-in step)'
    return 'Public'
route_rows = []
for r in routes:
    if r['uri'].startswith(('_ignition', 'sanctum', 'up')): continue
    mw = [m.split('\\')[-1] for m in r['middleware'] if m != 'web']
    perm = ', '.join(m.split(':', 1)[1] for m in mw if m.startswith(('EnsureAdminCan:', 'EnsureAdminRole:')))
    route_rows.append([r['method'].replace('|HEAD', ''), '/' + r['uri'].lstrip('/'), r['name'] or '', audience(r['middleware'], r['uri']), perm,
                       ', '.join(m for m in mw if not m.startswith(('EnsureAdminCan:', 'EnsureAdminRole:'))), (r['action'] or '').split('\\')[-1]])

perm_src = open(os.path.join(APP, 'app/Support/AdminPermissions.php')).read()
matrix = re.findall(r"'([a-z0-9_.]+)'\s*=>\s*\[([^\]]*)\]", perm_src.split('MATRIX', 1)[1].split('];', 1)[0])
roles_all = ['super_admin', 'hr_admin', 'records_reviewer', 'records_approver', 'auditor', 'lms_admin', 'reenlistment_admin', 'adjutant']
role_rows = [[cap] + ['Yes' if f"'{r}'" in lst else '' for r in roles_all] for cap, lst in matrix]
role_desc = re.findall(r"=> '([^']+ — [^']+)'", open(os.path.join(APP, 'app/Models/AdminUser.php')).read())

SOURCES = {
    'Landing page': 'resources/views/home*.blade.php, layouts/landing.blade.php, public/js/landing.js',
    'Applicant registration': 'Auth/RegisterController.php, Auth/LoginController.php, public/js/register.js',
    'Applicant application workflow': 'DashboardController::profile/soiPage, TempRegistrationController::saveStep1..6, SoiController::submit, public/js/soi-lock.js',
    'Upload documents and respond to returns': 'ApplicantDocumentController::store, App\\Support\\DocumentReview, applicant/upload-documents; 201: upload-201 route, App\\Support\\Review201',
    'Apply for training': 'applicant/training/index.blade.php, route applicant.training.store',
    'Administrator access': 'AdminAuthController / two-factor routes, layouts/admin.blade.php, public/js/pcg-navbar.js, HrmisOverview, PersonnelController',
    'Approve a self-registration': 'admin/dashboard.blade.php, public/js/admin-dashboard.js, POST /admin/approve/{id}, /admin/reject/{id}',
    'Review required documents (unit checker)': 'UnitDocumentController::review, unit/partials/review.blade.php, public/js/unit-checker.js',
    'Review 201 files (unit checker)': 'UnitDocumentController::review201, App\\Support\\Review201',
    'SOI submissions (Adjutant / HR admin)': 'SoiSubmissionController (index, reopen, edit, save)',
    'Unit management': 'OrganizationController (store, update, status), admin/organization/*, UnitUserController (unit accounts)',
    'Personnel registration processing': 'RegistrationController (store, submit, review, decide), admin/registrations/show.blade.php',
    'Verify 201 documents and run reports': 'PersonnelDocumentController (verify, download), ReportController',
    'Re-enlistment': 'ReenlistmentController (index, show, export), admin/reenlistment/*',
    'Phone screens': 'responsive layouts (pcg-ui.css)',
}
wf_rows = [[s['id'], s['slide'], s['section'], s['workflow'], f"{s['n']} of {s['total']}" if s['n'] else '', s['role'], s['url'], s['title'],
            s['action'], s['expected'], '; '.join(f"{i + 1}={b['label'] or b['kind']} ({b['kind']})" for i, b in enumerate(s['boxes'])),
            SOURCES.get(s['workflow'], ''), 'original/' + s['file']] for s in steps]

coverage = [
    ['1', 'Cover, overview, purpose, roles, process map', 'Verified (slides 1-6)', 'Roles from AdminPermissions::MATRIX and AdminUser::ROLES'],
    ['2', 'Open portal, navigation, public options, begin registration / sign-in', 'Verified', ''],
    ['3', 'Registration page, required info, verification (camera photo), submit, confirmation, log in, dashboard', 'Verified', 'Photo taken with a test camera (Chromium fake device). Login only works after HR approval.'],
    ['4', 'Select application type', 'Verified', 'Officer / Non-officer registration type'],
    ['4', 'Personal information, application fields', 'Verified', 'SOI six steps'],
    ['4', 'Upload required documents', 'Verified', 'Required documents and 201 files'],
    ['4', 'Review before submitting, submit', 'Verified', '"Is the data you entered correct?" prompt'],
    ['4', 'Track status', 'Verified', 'Dashboard review status and returned documents'],
    ['4', 'Respond to correction requests', 'Verified', 'Returned item reopens with the comment'],
    ['4', 'Apply for a recruitment position', 'NOT IMPLEMENTED', 'No position/vacancy application exists; training applications only'],
    ['5', 'Admin login, dashboard, navigation, roles, applicant database', 'Verified', 'Second step shown without any code or secret'],
    ['6', 'Open unit management, create unit, required info, save, confirm, edit', 'Verified', '/admin/organization (super admin only)'],
    ['6', 'Configure unit settings', 'Verified', 'Level, command group, effective dates, display order, description; activate/deactivate'],
    ['6', 'View unit-associated applicants', 'Verified', 'Personnel filtered by "Unit (incl. subordinate units)"'],
    ['6', 'Manage unit records per permissions', 'Verified', 'Unit checker accounts (create, M1 access, deactivate)'],
    ['7', 'Locate, search, filter, inspect applicants', 'Verified', 'Self-registrations table, unit queue filters, personnel filters'],
    ['7', 'Verify documents, record decisions and remarks', 'Verified', 'Unit review (approve / return with comment); 201 documents verify/reject with remarks'],
    ['7', 'Update applicant statuses', 'Verified', 'Pending -> Approved/Rejected; documents Pending -> Approved/Returned; SOI reopen'],
    ['7', 'Manage incomplete applications', 'Verified', 'Return items; reopen or edit a submitted SOI'],
    ['8', 'Processing stages and actions', 'Verified', 'Draft, Submitted, Returned for correction, Endorsed, Approved, Rejected'],
    ['8', 'Unit-related assignment', 'Verified', 'Proposed assignment (unit + effective date) becomes the personnel assignment on approval'],
    ['8', 'Reporting and exports', 'Verified', 'HR reports + Export CSV; HRMIS overview export; ETE export'],
    ['9', 'Eligible records and prerequisites', 'Verified', 'Non-officers with ETE'],
    ['9', 'Open the re-enlistment function, review details', 'Verified', ''],
    ['9', 'Submit required information/documents', 'Verified', 'The member SOI is the re-enlistment form'],
    ['9', 'Initiate re-enlistment', 'NOT IMPLEMENTED', 'No case/initiation action'],
    ['9', 'Verification / review / approval / disposition', 'NOT IMPLEMENTED', 'Module is read-only'],
    ['9', 'Resulting record and status', 'NOT IMPLEMENTED', 'No re-enlistment status stored'],
    ['10', 'Summary, common errors, role matrix, FAQ, checklist', 'Verified', 'Errors use messages captured or read from the code'],
    ['-', 'Product name "ROAP"', 'NOT VERIFIED', 'The application identifies itself as MEPCOM HRMIS; "ROAP" does not appear in the code or screens'],
]

wb = Workbook(); wb.remove(wb.active)
sheet(wb, 'Read me', ['Item', 'Value'], [
    ['System', 'MEPCOM HRMIS (requested as ROAP)'], ['Captured', data['captured']], ['Base URL', data['base'] + ' (development copy, pcg_core_dev, seeded sample data)'],
    ['Steps captured', str(len(steps))], ['Capture warnings', str(len(data['warnings'])) + (': ' + '; '.join(data['warnings']) if data['warnings'] else '')],
    ['Sheets', 'Workflows (one row per captured step), Routes (all application routes), Permission matrix, Admin roles, Coverage (requested storyboard vs. system)'],
], [22, 110])
color_status(sheet(wb, 'Coverage', ['Section', 'Requested item', 'Status', 'Evidence / note'], coverage, [9, 60, 18, 80]), 3)
sheet(wb, 'Workflows', ['Step id', 'Slide', 'Section', 'Workflow', 'Step', 'Role', 'Route', 'Page / step title', 'Action (exact labels)', 'Expected result', 'Annotated controls', 'Source (code)', 'Screenshot'], wf_rows,
      [9, 7, 8, 26, 9, 22, 30, 28, 50, 45, 40, 45, 26])
sheet(wb, 'Routes', ['Method', 'URI', 'Name', 'Audience', 'Required role / capability', 'Other middleware', 'Controller action'], route_rows, [10, 45, 34, 22, 40, 45, 40])
sheet(wb, 'Permission matrix', ['Capability'] + roles_all, role_rows, [24] + [15] * len(roles_all))
sheet(wb, 'Admin roles', ['Role description (AdminUser::ROLES)'], [[d] for d in role_desc] + [['Unit checker — reviews documents and 201 files of its unit (unit_users, /unit/login)'], ['M1 checker — reviews all units; disciplinary actions (/unit/loginM1)'], ['Applicant / member — users table, /login']], [120])
wb.save(os.path.join(OUT, 'ROAP_Workflow_Inventory.xlsx'))

# ------------------------------------------------------------ QA matrix
other_domains = re.compile(r'[A-Za-z0-9._%+-]+@(?!demo\.test)[A-Za-z0-9.-]+\.[a-z]{2,}')
secret = re.compile(r'(authenticator key|recovery code|otpauth://|[A-Z2-7]{16,})')
qa = []
for s in steps:
    found = len(s['boxes'])
    labels = ' '.join(s['visibleLabels'])
    pii = other_domains.search(labels)
    sec = secret.search(labels)
    ok = found > 0 or s['id'] in ('M-04', 'M-06')
    status = 'Passed' if ok and not s['health']['error'] and not s['health']['loading'] and not pii and not sec else 'Failed'
    notes = []
    if s.get('crop'): notes.append('slide shows a real crop (zoomed/)')
    if s['zoom']: notes.append('captured as a real crop')
    if s['id'] in ('S03-05', 'S03-06'): notes.append('camera image is the browser test pattern (fake camera device)')
    if s['id'] in ('M-04', 'M-06'): notes.append('orientation screen, no single target')
    qa.append([s['slide'], s['workflow'], s['role'], s['url'], s['shown'], 'Yes (performed in the browser)', status,
               f"{found} target(s) located on screen", 'No error text' if not s['health']['error'] else 'ERROR TEXT', 'No loading state' if not s['health']['loading'] else 'LOADING',
               'Sample data only' if not pii else 'CHECK: ' + pii.group(0), 'None visible' if not sec else 'CHECK', s['viewport'], s['id'], '; '.join(notes)])
ws = sheet(wb2 := Workbook(), 'Validation matrix', ['Slide No.', 'Workflow', 'User role', 'Source route', 'Screenshot', 'Action verified', 'Status', 'Arrow / outline target', 'Error state', 'Loading state', 'Personal data', 'Secrets', 'Viewport', 'Step id', 'Notes'], qa,
           [8, 30, 24, 30, 26, 18, 10, 22, 14, 14, 18, 12, 11, 9, 40])
wb2.remove(wb2['Sheet'])
color_status(ws, 7)
sheet(wb2, 'Deck checks', ['Check', 'Result', 'How'], [
    ['File validation (OOXML schema, relationships, content types)', 'See README / validate.py output', 'python validate.py deck.pptx'],
    ['Every slide rendered (LibreOffice -> PDF)', 'See manual PDF page count = slide count', 'soffice --convert-to pdf'],
    ['Text overflow / overlap', 'Reviewed visually on all slides', 'render.sh grids'],
    ['Arrows point at the real control', 'Arrow end = on-screen box of the control recorded during capture', 'capture.js boundingBox() per target'],
    ['Labels match the application', 'Action texts use the labels read from the page (visibleLabels in steps.json)', 'capture.js'],
], [50, 60, 40])
wb2.save(os.path.join(OUT, 'ROAP_QA_Validation.xlsx'))
print('annotated', len(steps), 'inventory rows', len(wf_rows), 'routes', len(route_rows), 'qa failed', sum(1 for r in qa if r[6] != 'Passed'))
