<li>
    @include('performance-talent.successors._position-card', ['position' => $position])

    @if ($position['children'] !== [])
        <ul>
            @foreach ($position['children'] as $childPosition)
                @include('performance-talent.successors._tree-node', ['position' => $childPosition])
            @endforeach
        </ul>
    @endif
</li>
