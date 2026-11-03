@props(['showSelectionColumn' => false])

<colgroup>
    @if ($showSelectionColumn)
        <col class="w-12">
    @endif
    <col class="w-24">
    <col class="w-44">
    <col class="w-52">
    <col class="w-52">
    <col class="w-60">
    <col class="w-48">
</colgroup>
