{{--
  The crew member's CV, laid out like the agency's "Template CV" Word form:
  A. Personal Data · B. Certificate of Competency · C. Certificate of Proficiency ·
  D. Sea Service Record · the declaration. Rendered to PDF by dompdf, so the CSS stays
  to what it understands: tables, borders, DejaVu/Times fonts, no flex or grid.
--}}
@php
  $d = function ($value) {
      if (blank($value)) { return ''; }
      try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y'); }
      catch (\Throwable) { return (string) $value; }
  };
  $v = fn ($value) => filled($value) ? e($value) : '';
  $placeDate = fn (array $c) => trim(implode(', ', array_filter([$c['issued_by'] ?? null, $d($c['issue_date'] ?? null)])));
  $contact = implode(' / ', array_filter([$crew->personal_email, $crew->cell_number]));
  $seaRows = max($seaService->count(), \App\Services\CrewCv::SEA_SERVICE_ROWS);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CV - {{ $crew->name }}</title>
<style>
  @page { margin: 18mm 16mm 16mm 16mm; }
  body { font-family: 'Times New Roman', Times, serif; font-size: 10.5pt; color: #000; margin: 0; }
  h1 { text-align: center; font-size: 15pt; font-weight: bold; text-decoration: underline; letter-spacing: 1px; margin: 0 0 22px; }
  h2 { font-size: 10.5pt; font-weight: bold; margin: 18px 0 6px; }
  h2.first { margin-top: 10px; }
  table { border-collapse: collapse; width: 100%; page-break-inside: auto; }
  tr { page-break-inside: avoid; }
  td, th { border: 1px solid #000; padding: 4px 6px; vertical-align: top; font-size: 10pt; }
  th { font-weight: bold; text-align: center; }
  td.lbl { background: #f2f2f2; font-weight: bold; width: 29%; }
  td.sub { font-weight: bold; }
  td.sub span { font-weight: normal; }
  .tall td { height: 22px; }
  .head { width: 100%; border: 0; margin-bottom: 8px; }
  .head td { border: 0; padding: 0; vertical-align: top; }
  .photo { width: 3.2cm; height: 4.2cm; border: 1px solid #000; }
  .photo img { width: 3.2cm; height: 4.2cm; object-fit: cover; }
  .post { border: 1px solid #000; padding: 6px 10px; font-size: 13pt; width: 9.5cm; }
  .cop td.name { background: #f2f2f2; font-family: 'DejaVu Sans', Helvetica, sans-serif; font-size: 9pt; }
  .sea td.num { text-align: center; }
  .sea td.right { text-align: right; }
  .decl { margin-top: 18px; text-align: justify; line-height: 1.35; }
  .sig { margin-top: 10px; }
  .sig p { margin: 0 0 18px; }
  .muted { color: #555; }
</style>
</head>
<body>
  <h1>CURRICULUM VITAE</h1>

  <table class="head">
    <tr>
      <td style="width: 3.4cm">
        <div class="photo">@if($photo)<img src="{{ $photo }}" alt="">@endif</div>
      </td>
      <td style="padding: 14px 0 0 16px">
        <div class="post">Post Applied: {{ $v($crew->rank) }}</div>
      </td>
    </tr>
  </table>

  <h2 class="first">A. PERSONAL DATA</h2>
  <table>
    <tr><td class="lbl">Full Name</td><td colspan="3">{{ $v($crew->name) }}</td></tr>
    <tr class="tall"><td class="lbl">Present Address</td><td colspan="3">{!! nl2br($v($crew->current_address)) !!}</td></tr>
    <tr><td class="lbl">E-mail Address / Mobile phone</td><td colspan="3">{{ $contact }}</td></tr>
    <tr><td class="lbl">Place / Date of Birth</td><td colspan="2">{{ $v($crew->place_of_birth ?? null) }}</td><td style="width: 22%">{{ $d($crew->date_of_birth) }}</td></tr>
    <tr>
      <td class="lbl">Marital Status</td>
      <td style="width: 16%">{{ $v($crew->marital_status) }}</td>
      <td class="sub" style="width: 33%"><table style="border:0"><tr><td style="border:0;padding:0;width:50%"><b>Height:</b> {{ $v($crew->height ?? null) }}</td><td style="border:0;padding:0"><b>Weight:</b> {{ $v($crew->weight ?? null) }}</td></tr></table></td>
      <td class="sub">Gender: <span>{{ $v($crew->gender) }}</span></td>
    </tr>
    <tr>
      <td class="lbl">Shoes size</td>
      <td>{{ $v($crew->shoes_size ?? null) }}</td>
      <td class="sub">Boiler suit size: <span>{{ $v($crew->boiler_suit_size ?? null) }}</span></td>
      <td></td>
    </tr>
    <tr><td class="lbl">Religion</td><td colspan="3">{{ $v($crew->religion ?? null) }}</td></tr>
    <tr>
      <td class="lbl">Passport No</td>
      <td>{{ $v($passport['certificate_number'] ?? null) }}</td>
      <td class="sub">Issue Date: <span>{{ $d($passport['issue_date'] ?? null) }}</span></td>
      <td class="sub">Exp. Date: <span>{{ $d($passport['expiry_date'] ?? null) }}</span></td>
    </tr>
    <tr>
      <td class="lbl">Seaman Book No.</td>
      <td>{{ $v($seamanBook['certificate_number'] ?? null) }}</td>
      <td class="sub">Issue Date: <span>{{ $d($seamanBook['issue_date'] ?? null) }}</span></td>
      <td class="sub">Exp. Date: <span>{{ $d($seamanBook['expiry_date'] ?? null) }}</span></td>
    </tr>
    <tr>
      <td class="lbl">Next Of Kin</td>
      <td colspan="2">{{ $v($crew->person_to_be_contacted) }}@if($crew->emergency_phone_number) <span class="muted">· {{ $crew->emergency_phone_number }}</span>@endif</td>
      <td>Relationship: {{ $v($crew->relation) }}</td>
    </tr>
    <tr><td class="lbl">Address</td><td colspan="3">{!! nl2br($v($crew->permanent_address)) !!}</td></tr>
  </table>

  <h2>B. CERTIFICATE OF COMPETENCY</h2>
  <table>
    <tr>
      <th style="width: 33%">Certificate</th>
      <th style="width: 21%">Number</th>
      <th style="width: 26%">Place / Date of Issue</th>
      <th style="width: 20%">Expiry Date</th>
    </tr>
    @foreach($competency as $c)
      <tr class="tall">
        <td class="lbl" style="width:auto; font-weight: normal">{{ $v($c['certificate_type'] ?? null) }}</td>
        <td>{{ $v($c['certificate_number'] ?? null) }}</td>
        <td>{{ $placeDate($c) }}</td>
        <td>{{ $d($c['expiry_date'] ?? null) }}</td>
      </tr>
    @endforeach
    @for($i = count($competency); $i < 3; $i++)
      <tr class="tall"><td class="lbl" style="width:auto"></td><td></td><td></td><td></td></tr>
    @endfor
  </table>

  <h2>C. CERTIFICATE OF PROFICIENCY</h2>
  <table class="cop">
    <tr>
      <th style="width: 44%">Certificate</th>
      <th style="width: 26%">Number</th>
      <th style="width: 30%">Place / Date of Issue</th>
    </tr>
    @foreach($proficiency as $row)
      <tr>
        <td class="name">{{ $row['name'] }}</td>
        <td>{{ $v($row['certificate']['certificate_number'] ?? null) }}</td>
        <td>{{ $row['certificate'] ? $placeDate($row['certificate']) : '' }}</td>
      </tr>
    @endforeach
  </table>

  <h2>D. SEA SERVICE RECORD:</h2>
  <table class="sea">
    <tr>
      <th rowspan="2" style="width: 6%">No.</th>
      <th rowspan="2" style="width: 13%">Rank</th>
      <th rowspan="2" style="width: 20%">Ship Name</th>
      <th rowspan="2" style="width: 19%">Company</th>
      <th rowspan="2" style="width: 11%">Ship Type</th>
      <th rowspan="2" style="width: 9%">GRT</th>
      <th colspan="2">Period</th>
    </tr>
    <tr>
      <th style="width: 11%">Sign On</th>
      <th style="width: 11%">Sign Off</th>
    </tr>
    @for($i = 0; $i < $seaRows; $i++)
      @php $b = $seaService[$i] ?? null; $ship = $b?->vessel_details ?? []; @endphp
      <tr class="tall">
        <td class="num">{{ $i + 1 }}.</td>
        <td>{{ $v($b?->rank) }}</td>
        <td>{{ $b ? $v($ship['vessel_name'] ?? $b->vessel) : '' }}</td>
        <td>{{ $b ? $v($ship['principal'] ?? $b->company) : '' }}</td>
        <td>{{ $v($ship['vessel_type'] ?? null) }}</td>
        <td class="right">{{ filled($ship['gross_tonnage'] ?? null) ? number_format((float) $ship['gross_tonnage']) : '' }}</td>
        <td>{{ $d($b?->sign_on_date) }}</td>
        <td>{{ $b?->sign_off_date ? $d($b->sign_off_date) : ($b ? 'Onboard' : '') }}</td>
      </tr>
    @endfor
  </table>

  <p class="decl">
    I certify that all statement given on this application are correct and true to my knowledge. I also understand that
    falsification or misrepresentation (intentionally or unintentionally) in this my personal record can result my
    immediate dismissal and forfeiture of all wages, allowances and benefits if I am employed by the company. I do
    agree to submit myself to a through medical examination, which I must successfully pass one of the condition
    for being accepted for employment.
  </p>

  <div class="sig">
    <p>Note:</p>
    <p>Thank you very much, very happy and interested I can served and join to your good company.</p>
    <p>Sincerely yours,</p>
    <p style="margin-top: 28px">{{ $v($crew->name) }}</p>
  </div>
</body>
</html>
