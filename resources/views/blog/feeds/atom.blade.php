@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><![CDATA[{{ $siteTitle }}]]></title>
  <link href="{{ $siteUrl }}" rel="alternate" />
  <link href="{{ route('blog.atom') }}" rel="self" type="application/atom+xml" />
  <updated>{{ optional($updatedAt)->toAtomString() }}</updated>
  <id>{{ $siteUrl }}</id>

  @foreach ($items as $item)
    <entry>
      <title><![CDATA[{{ $item['title'] }}]]></title>
      <link href="{{ $item['url'] }}" />
      <id>{{ $item['id'] }}</id>
      <updated>{{ optional($item['published_at'])->toAtomString() }}</updated>
      <summary type="html"><![CDATA[{{ $item['summary'] }}]]></summary>
      <content type="html"><![CDATA[{!! $item['content'] !!}]]></content>
      @if (!empty($item['author']))
        <author>
          <name><![CDATA[{{ $item['author'] }}]]></name>
        </author>
      @endif
      @foreach ($item['tags'] as $tag)
        <category term="{{ $tag }}" />
      @endforeach
    </entry>
  @endforeach
</feed>
