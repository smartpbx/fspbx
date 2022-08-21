@extends('layouts.singlepage', ["page_title"=> "Caller ID"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                {{-- <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Hyper</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Tables</a></li>
                        <li class="breadcrumb-item active">Basic Tables</li>
                    </ol>
                </div> --}}
                <h4 class="page-title">Settings</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">User Settings</h4>
                    {{-- <p class="text-muted font-14">
                        For basic styling—light padding and only horizontal dividers—add the base class <code>.table</code> to any <code>&lt;table&gt;</code>.
                    </p> --}}

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#basic-example-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Caller ID
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a href="#basic-example-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li> --}}
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="basic-example-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th>Phone Number</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($destinations as $destination)
                                            <tr>
                                                <td>{{ $destination->destination_description }}</td>
                                                <td>{{ phone($destination->destination_number,"US",$national_phone_number_format) }}</td>
                                                <td>
                                                    <!-- Switch-->
                                                    <div>
                                                        {{-- <input type="checkbox" id="@php print 'switch'.$i; @endphp" class="callerIdCheckbox"
                                                            @if ($destination['isCallerID']) checked @endif 
                                                            value="{{ $destination['destination_uuid'] }}"
                                                            data-switch="success" /> --}}

                                                        <input type="checkbox" id="checkbox{{ $destination->destination_uuid }}" data-switch="success" class="callerIdCheckbox" 
                                                            onclick="javascript:changeCallerId('{{ route('updateCallerID', $extension )}}','{{ $destination->destination_uuid }}', this);"
                                                            @if ($destination->isCallerID) checked @endif >
                                                        <label for="checkbox{{ $destination->destination_uuid }}" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>

                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                       
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                       
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->


    </div>
    <!-- end row-->


</div> <!-- container -->
@endsection


@push('scripts')
<script>
    $(document).ready(function() {
        //CallerID single page. 
        //uncheck all of the checkboxes, apart from the one checked
        // $('input.callerIdCheckbox').on('change', function() {
        //     var id = $(this).val();
        //     var checkbox = $(this);


        //     var formData = new FormData();
        //     formData.append('extension_uuid', extension_uuid);
        //     formData.append('destination_uuid', id); 

        //     $.ajax({
        //         type : "POST",
        //         url : url,
        //         data: formData,
        //         processData: false,
        //         contentType: false,
        //         checkbox : $(this),
        //     })
        //     .done(function(response) { 
        //         console.log(response);
        //         if (response.error){
        //             checkbox.prop('checked', false);
        //         } else {
        //             $('input.callerIdCheckbox').not(checkbox).prop('checked', false);
        //         }
        //     })
        //     .fail(function (response){
        //         //
        //     });

        // });
    });

    // Send request to change Caller ID
    function changeCallerId(url, destination, checkbox){
        var checked = $("#"+checkbox.id).is(":checked");
        $.ajax({
                type : "POST",
                url : url,
                data: {
                        'destination_uuid' : destination,
                        'set' : checked,
                    },
            })
            .done(function(response) {
                // console.log(response);
                
                if (response.error) {
                    checkbox.prop('checked', false);
                    printErrorMsg(response.error.message);
                }

                if (response.success) {
                    $('input.callerIdCheckbox').not(checkbox).prop('checked', false);
                    $.NotificationApp.send("Success",response.success.message,"top-right","#10c469","success");
                }
                
            })
            .fail(function (jqXHR, testStatus, error) {
                    // console.log(error);
                    printErrorMsg(error);
            });
    }   

</script>
@endpush