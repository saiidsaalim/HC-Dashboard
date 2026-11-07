@foreach ($successors as $successor)
    @php($successorInitial = mb_strtoupper(mb_substr(trim($successor->name), 0, 1)))
    @php($successorPosition = $successor->current_position ?: $successor->position)
    @php($successorEducation = $successor->last_education ?: $successor->education)
    @php($successorTalentBox = $successor->talent_box ?: $successor->talent_mapping)
    @php($successorHistory = collect($successor->position_history ?? [])->take(3))
    @include('performance-talent.successors._candidate-preview')
@endforeach
