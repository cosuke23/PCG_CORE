# MEPCOM subordinate units — proposed import

Source: the unit list in the registration form (`resources/views/pages/register.blade.php`, "based on your CSV").
Approved for import by the owner on 2026-10-31. Data file: `database/data/mepcom_units.json` (edit it to correct anything before importing).

## How it was interpreted (please check)

- **O/C, O/DC, O/CS, O/D, O/DD, O/S, O/AS** are read as *Offices* (of the Commander, Deputy Commander, Chief of Staff, Director, Deputy Director, Superintendent, Assistant Superintendent), not as positions.
- **LEF-n, N-n, S-n** are staff sections under each unit's headquarters.
- Grouping labels in the dropdown are **not** separate units: "MEPLEF DIVISION", "Operational Support Unit", "Administrative Support Unit", "MEPLEU", "OSRU", "MSFIU". Their entries are placed directly under the unit.
- "Operational/Administrative Support Units" and "Operating Unit" are kept as each unit's description (command group), not as levels.
- **To confirm:** "BICIO Headquarters Support Group" is listed under NOCOP in the source; "N-1, Adjutant" is kept as written; MEPG lists only two groups (NCRCL, NELZN).

## Import (on the PC, after a backup)

```powershell
php artisan pcg:import-org-units database/data/mepcom_units.json
php artisan pcg:import-org-units database/data/mepcom_units.json --apply
```

Insert-only: existing units are never changed, moved or deleted, and running it again creates nothing new.

## Proposed tree (140 units under MEPCOM)

- `MEPLEF` — Marine Environmental Protection Law Enforcement Force *(unit)*
  - `HMEPLEF` — Headquarters, MEPLEF *(office)*
    - `MEPLEF O/C` — Office of the Commander, MEPLEF *(section)*
    - `MEPLEF O/DC` — Office of the Deputy Commander, MEPLEF *(section)*
    - `MEPLEF O/CS` — Office of the Chief of Staff, MEPLEF *(section)*
    - `MEPLEF LEF-1` — LEF-1 *(section)*
    - `MEPLEF LEF-2` — LEF-2 *(section)*
    - `MEPLEF LEF-3` — LEF-3 *(section)*
    - `MEPLEF LEF-4` — LEF-4 *(section)*
    - `MEPLEF LEF-5` — LEF-5 *(section)*
    - `MEPLEF LEF-6` — LEF-6 *(section)*
    - `MEPLEF LEF-11` — LEF-11 *(section)*
    - `MEPLEF LEF-12` — LEF-12 *(section)*
  - `MEPLEF MICD` — MEP Inspection and Compliance Division *(office)*
  - `MEPLEF MEICTSD` — MEP Enforcement Information & Communications Technology System Division *(office)*
  - `MEPLEF APAD` — Adjudication and Prosecution Assistance Division *(office)*
  - `MEPLEF CEOD` — Community Engagement Outreach Division *(office)*
  - `MEPLEF HSG` — Headquarters Support Group *(office)*
  - `MEPLEF MPO` — MEPLEF Procurement Office *(office)*
  - `MEPLEU NCR-CL` — MEPLEU NCR-CL *(unit)*
  - `MEPLEU NWLZN` — MEPLEU NWLZN *(unit)*
  - `MEPLEU NELZN` — MEPLEU NELZN *(unit)*
  - `MEPLEU STL` — MEPLEU STL *(unit)*
  - `MEPLEU BCL` — MEPLEU BCL *(unit)*
  - `MEPLEU PAL` — MEPLEU PAL *(unit)*
  - `MEPLEU KIG` — MEPLEU KIG *(unit)*
  - `MEPLEU CV` — MEPLEU CV *(unit)*
  - `MEPLEU EV` — MEPLEU EV *(unit)*
  - `MEPLEU WV` — MEPLEU WV *(unit)*
  - `MEPLEU SV` — MEPLEU SV *(unit)*
  - `MEPLEU NM` — MEPLEU NM *(unit)*
  - `MEPLEU NEM` — MEPLEU NEM *(unit)*
  - `MEPLEU SWM` — MEPLEU SWM *(unit)*
  - `MEPLEU SM` — MEPLEU SM *(unit)*
  - `MEPLEU BARMM` — MEPLEU BARMM *(unit)*
- `NOCOP` — National Operations Center for Oil Pollution *(unit)*
  - `HNOCOP` — Headquarters, NOCOP *(office)*
    - `NOCOP O/D` — Office of the Director, NOCOP *(section)*
    - `NOCOP O/DD` — Office of the Deputy Director, NOCOP *(section)*
    - `NOCOP O/CS` — Office of the Chief of Staff, NOCOP *(section)*
    - `NOCOP N-1` — N-1, Adjutant *(section)*
    - `NOCOP N-2` — N-2 *(section)*
    - `NOCOP N-3` — N-3 *(section)*
    - `NOCOP N-4` — N-4 *(section)*
    - `NOCOP N-5` — N-5 *(section)*
    - `NOCOP N-6` — N-6 *(section)*
    - `NOCOP N-11` — N-11 *(section)*
    - `NOCOP N-12` — N-12 *(section)*
  - `NOCOP MPRRAC` — Marine Pollution Response Risk Assessment Center *(office)*
  - `NOCOP NOPRC` — National Oil Pollution Response Center *(office)*
  - `NOCOP MPRC` — Marine Pollutant Response Center *(office)*
  - `NOCOP MCC` — MARPOL Coordinating Center *(office)*
  - `NOCOP NCC` — NOCOP Communications Center *(office)*
  - `NOCOP BHSG` — BICIO Headquarters Support Group *(office)*
  - `NOCOP NPO` — NOCOP Procurement Office *(office)*
  - `OSRU NCR-CL` — OSRU NCR-CL *(unit)*
  - `OSRU NWLZN` — OSRU NWLZN *(unit)*
  - `OSRU NELZN` — OSRU NELZN *(unit)*
  - `OSRU STL` — OSRU STL *(unit)*
  - `OSRU BCL` — OSRU BCL *(unit)*
  - `OSRU PAL` — OSRU PAL *(unit)*
  - `OSRU KIG` — OSRU KIG *(unit)*
  - `OSRU CV` — OSRU CV *(unit)*
  - `OSRU EV` — OSRU EV *(unit)*
  - `OSRU WV` — OSRU WV *(unit)*
  - `OSRU SV` — OSRU SV *(unit)*
  - `OSRU NM` — OSRU NM *(unit)*
  - `OSRU NEM` — OSRU NEM *(unit)*
  - `OSRU SWM` — OSRU SWM *(unit)*
  - `OSRU SM` — OSRU SM *(unit)*
  - `OSRU BARMM` — OSRU BARMM *(unit)*
- `MSFIS` — Marine Science Forensic Investigation Service *(unit)*
  - `HMSFIS` — Headquarters, MSFIS *(office)*
    - `MSFIS O/C` — Office of the Commander, MSFIS *(section)*
    - `MSFIS O/DC` — Office of the Deputy Commander, MSFIS *(section)*
    - `MSFIS O/CS` — Office of the Chief of Staff, MSFIS *(section)*
    - `MSFIS S-1` — S-1 *(section)*
    - `MSFIS S-2` — S-2 *(section)*
    - `MSFIS S-3` — S-3 *(section)*
    - `MSFIS S-4` — S-4 *(section)*
    - `MSFIS S-5` — S-5 *(section)*
    - `MSFIS S-6` — S-6 *(section)*
    - `MSFIS S-11` — S-11 *(section)*
    - `MSFIS S-12` — S-12 *(section)*
  - `MSFIS FSD` — Field Scientific Division *(office)*
  - `MSFIS MSUID` — Marine Science Underwater Investigation Division *(office)*
  - `MSFIS MLC` — MSFIS Laboratory Center *(office)*
  - `MSFIS MSC` — Marine Science Custody, Receiving and Releasing Division *(office)*
  - `MSFIS FID` — Forensic and Investigation Division *(office)*
  - `MSFIS RDD` — Research and Development Division *(office)*
  - `MSFIS MSHSG` — MSFI Service Headquarter Support Group *(office)*
  - `MSFIS MPO` — MSFIS Procurement Office *(office)*
  - `MSFIU NCR-CL` — MSFIU NCR-CL *(unit)*
  - `MSFIU NWLZN` — MSFIU NWLZN *(unit)*
  - `MSFIU NELZN` — MSFIU NELZN *(unit)*
  - `MSFIU STL` — MSFIU STL *(unit)*
  - `MSFIU BCL` — MSFIU BCL *(unit)*
  - `MSFIU PAL` — MSFIU PAL *(unit)*
  - `MSFIU KIG` — MSFIU KIG *(unit)*
  - `MSFIU CV` — MSFIU CV *(unit)*
  - `MSFIU EV` — MSFIU EV *(unit)*
  - `MSFIU WV` — MSFIU WV *(unit)*
  - `MSFIU SV` — MSFIU SV *(unit)*
  - `MSFIU NM` — MSFIU NM *(unit)*
  - `MSFIU NEM` — MSFIU NEM *(unit)*
  - `MSFIU SWM` — MSFIU SWM *(unit)*
  - `MSFIU SM` — MSFIU SM *(unit)*
  - `MSFIU BARMM` — MSFIU BARMM *(unit)*
- `MEPRCGS` — Marine Environmental Protection Regulatory Compliance and Governance Service *(unit)*
  - `MEPRCGS O/D` — Office of the Director, MEP RCGS *(section)*
  - `MEPRCGS O/DD` — Office of the Deputy Director, MEP RCGS *(section)*
  - `MEPRCGS ARMB` — Administrative and Resource Management Branch *(office)*
  - `MEPRCGS CCBED` — Climate Change and Blue Economy Division *(office)*
  - `MEPRCGS RCD` — Regulatory Compliance Division *(office)*
  - `MEPRCGS GMD` — Governance & Management Division *(office)*
- `MEPTDI` — Marine Environmental Protection Training and Doctrine Institute *(unit)*
  - `MEPTDI O/S` — Office of the Superintendent, MEPTDI *(section)*
  - `MEPTDI O/AS` — Office of the Assistant Superintendent, MEPTDI *(section)*
  - `MEPTDI ARMD` — Administrative and Resource Management Division *(office)*
  - `MEPTDI RDDD` — Research and Doctrine Development Division *(office)*
  - `MEPTDI STD` — Special Training Division *(office)*
  - `MEPTDI ICTD` — Information and Communications Technology Division *(office)*
- `MEPG` — Marine Environmental Protection Group *(unit)*
  - `MEPG NCRCL` — MEP Group NCRCL *(unit)*
    - `HMEPG NCRCL` — Headquarters, MEP Group NCRCL *(office)*
    - `MEPUnit Manila` — MEPUnit Manila *(unit)*
    - `MEPUnit Cavite` — MEPUnit Cavite *(unit)*
    - `MEPUnit Bataan` — MEPUnit Bataan *(unit)*
    - `MEPUnit Zambales` — MEPUnit Zambales *(unit)*
    - `MEPUnit Laguna` — MEPUnit Laguna *(unit)*
    - `MEPUnit Rizal` — MEPUnit Rizal *(unit)*
    - `MEPUnit Malacanang` — MEPUnit Malacañang *(unit)*
    - `MEPUnit Pampanga` — MEPUnit Pampanga *(unit)*
  - `MEPG NELZN` — MEP Group NELZN *(unit)*
    - `HMEPG NELZN` — Headquarters, MEP Group NELZN *(office)*
    - `MEPUnit Cagayan` — MEPUnit Cagayan *(unit)*
    - `MEPUnit Calayan` — MEPUnit Calayan *(unit)*
    - `MEPUnit Aurora` — MEPUnit Aurora *(unit)*
    - `MEPUnit Batanes` — MEPUnit Batanes *(unit)*
    - `MEPUnit Isabela` — MEPUnit Isabela *(unit)*
