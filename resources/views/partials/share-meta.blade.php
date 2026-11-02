{{-- Link previews (Messenger, Facebook, Viber, Teams, X ...): always the MEPCOM seal,
     never a random image from the page. Absolute URLs come from the request host. --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="MEPCOM HRMIS">
<meta property="og:title" content="{{ $shareTitle ?? 'MEPCOM HRMIS · Philippine Coast Guard' }}">
<meta property="og:description" content="Personnel information system of the PCG Marine Environmental Protection Command: registration, service records, document review and training.">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/brand/og-mepcom.jpg') }}">
<meta property="og:image:secure_url" content="{{ secure_asset('images/brand/og-mepcom.jpg') }}">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Seal of the Marine Environmental Protection Command, Philippine Coast Guard">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $shareTitle ?? 'MEPCOM HRMIS · Philippine Coast Guard' }}">
<meta name="twitter:image" content="{{ asset('images/brand/og-mepcom.jpg') }}">
<link rel="apple-touch-icon" href="{{ asset('images/brand/og-mepcom-square.jpg') }}">
