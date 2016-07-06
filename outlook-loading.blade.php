@extends('layouts.bootstrap')

@section('content')
    {{-- Loading --}}
    <div id="loadingOverlay" class="modal-backdrop" style="z-index:10000; background-color: rgba(45, 45, 45, 0.75); color:#fcfcfc;text-align:center;">
        <div style="margin:100px 10px 10px 10px;font-size:32px;">
            <span class="glyphicon glyphicon-refresh glyphicon-refresh-animate"></span> Syncing...
        </div>
        <p style="font-size:18px">This may take a few minutes</p>
    </div>

    {{-- Script to redirect to ACTUAL loading script --}}
    <script>
        window.location = '/outlookrealsync';
    </script>
@stop