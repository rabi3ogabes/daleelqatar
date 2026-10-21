<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($parts as $part)
  <sitemap>
    <loc>{{ route('sitemap.part', ['part' => $part['name']]) }}</loc>
@if($part['lastmod'])
    <lastmod>{{ $part['lastmod'] }}</lastmod>
@endif
  </sitemap>
@endforeach
</sitemapindex>
