<div class="inpt form-group">
    @if($label)
        <label for="{{ $id ?? $name }}">{!! $label !!}</label>
    @endif

    @if($type === 'select')
        @if($type == 'select' && !empty($options))
            <select name="{{ $name }}" class="form-control {{ $class }}" id="{{ $id ?? $name }}" {{ $readonly ? 'readonly' : '' }}>
                @if (is_array($options))
                    <option value='' selected>Seleccione</option>
                    @foreach($options as $key => $option)
                        <option value="{{ $key }}" {{ old($name) == $key ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                @else
                    {!! $options !!}
                @endif
            </select>
        @endif
    @elseif($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $id ?? $name }}" class=" form-control {{ $class }}" {{ $required ? 'required' : '' }} {{ $readonly ? 'readonly' : '' }} placeholder="{{ $placeholder }}">{{ $value }}</textarea>
        @if( $charlimit != 0 )
            <script>inputs.controlInsert("{{ $id ?? $name }}", "{{ $charlimit }}");</script>
        @endif
    @else
        <input
            type="{{ $type }}"
            class=" form-control {{ $class }}"
            name="{{ $name }}"
            id="{{ $id ?? $name }}"
            value="{{ $value ?? '' }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $readonly ? 'readonly' : '' }}
        />
        @if( $charlimit != 0 )
            <script>inputs.controlInsert("{{ $id ?? $name }}", "{{ $charlimit }}");</script>
        @endif
    @endif
</div>
