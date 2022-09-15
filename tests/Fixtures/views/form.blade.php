{{ $title }}

<form method="post" action="{{ route('submit') }}">
    @csrf
    <input
            type="hidden"
            name="form_step"
            value="{{ $form->currentStep() }}">

    @switch($form->currentStep())
        @case(1)
        <label>Name</label>
        <input
                type="text"
                name="name"
                value="{{ $form->getValue('name') }}">
        {{ $errors->first('name') }}
        @break
        @case(2)
        <label>Role</label>
        <input
                type="text"
                name="role"
                value="{{ $form->getValue('role') }}">
        {{ $errors->first('role') }}
        @break
        @case(3)
        Name: {{ $form->getValue('name') }}<br>
        Role: {{ $form->getValue('role') }}<br>
        @break
    @endswitch

    @if($form->isStep(3))
        <button type="submit" name="submit">Save</button>
        <button type="submit" name="submit" value="reset">Reset</button>
    @else
        <button type="submit">Continue</button>
    @endif
    <hr>

    {{ $form->toCollection() }}
</form>