<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($entries as $e)
@foreach(['ar', 'en'] as $lang)
  <url>
    <loc>{{ $e[$lang] }}</loc>
    <xhtml:link rel="alternate" hreflang="ar" href="{{ $e['ar'] }}"/>
    <xhtml:link rel="alternate" hreflang="en" href="{{ $e['en'] }}"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{{ $e['ar'] }}"/>
@if($e['lastmod'])
    <lastmod>{{ $e['lastmod'] }}</lastmod>
@endif
    <priority>{{ $e['priority'] }}</priority>
  </url>
@endforeach
@endforeach
</urlset>
