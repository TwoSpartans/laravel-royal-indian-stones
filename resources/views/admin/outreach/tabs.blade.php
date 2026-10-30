<nav class="outreach-tabs" aria-label="Outreach sections">
  <a href="{{ route('admin.outreach.index') }}" @if($section === 'pages') aria-current="page" @endif>Pages</a>
  <a href="{{ route('admin.outreach.mentions.index') }}" @if($section === 'mentions') aria-current="page" @endif>Mentions</a>
  <a href="{{ route('admin.outreach.profiles.index') }}" @if($section === 'profiles') aria-current="page" @endif>Profiles</a>
  <a href="{{ route('admin.outreach.topics.index') }}" @if($section === 'topics') aria-current="page" @endif>Topics</a>
</nav>
