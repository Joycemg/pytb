@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><![CDATA[{{ $siteTitle }}]]></title>
    <link>{{ $siteUrl }}</link>
    <atom:link href="{{ route('blog.rss') }}" rel="self" type="application/rss+xml" />
    <description><![CDATA[Novedades y comunicados publicados en {{ config('app.name', 'La Taberna') }}.]]></description>
    <language>es</language>
    <lastBuildDate>{{ optional($updatedAt)->toRfc2822String() }}</lastBuildDate>
    <generator>La Taberna</generator>

    @foreach ($items as $item)
      <item>
        <title><![CDATA[{{ $item['title'] }}]]></title>
        <link>{{ $item['url'] }}</link>
        <guid isPermaLink="true">{{ $item['id'] }}</guid>
        <pubDate>{{ optional($item['published_at'])->toRfc2822String() }}</pubDate>
        <description><![CDATA[{{ $item['summary'] }}]]></description>
        @if (!empty($item['author']))
          <author><![CDATA[{{ $item['author'] }}]]></author>
        @endif
        @foreach ($item['tags'] as $tag)
          <category><![CDATA[{{ $tag }}]]></category>
        @endforeach
      </item>
    @endforeach
  </channel>
</rss>
