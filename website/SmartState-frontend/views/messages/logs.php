<?php
/** @var UserSession $userSession */
$page = 'messages-log';
include_once __DIR__ . '/../_header.php';
?>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h4 mr-auto p-2">Messages - <span class="text-muted">Logs</span></h1>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3 form-floating">
            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                <select class="selectpicker" data-width="fit" id="select-participant-message" data-none-selected-text="Select Participant" data-live-search="true" data-live-search-placeholder="Search"></select>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <h5 id="time-zone" class="float-end"></h5>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <table id="collection" class="table table-bordered dt-responsive responsive-text" style="width:100%">
                <thead>
                <tr>
                    <th>Outgoing/Incoming</th>
                    <th style="text-align: center;">Message Body</th>
                    <th style="text-align: center;">Timestamp</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                <tr>
                    <th>Outgoing/Incoming</th>
                    <th style="text-align: center;">Message Body</th>
                    <th style="text-align: center;">Timestamp</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <script type="text/javascript">
        var messageModal = $('#messageModal');
        var messageModalParticipant = $('#messageModalParticipant');
        var messageModalMessage = $('#messageModalMessage');

        var collection = {};
        var collectionTable = $('#collection');
        var collectionDataTable = null;

        function updateDT() {
            let uuid = $('#select-participant-message').val();

            collectionDataTable = $('#collection').DataTable({
                destroy: true,
                serverSide: true,
                processing: true,
                ajax: {
                    url: "/messages/get-message-log",
                    type: "GET",
                    data: {
                        'uuid': uuid
                    },
                },
                order: [[ 2, "desc" ]],
                responsive: true,
                buttons: [
                    'pageLength','colvis', 'csv'
                ],
                layout: {
                    topStart: 'buttons',
                },
                columnDefs: [
                    {
                        className: "dt-center",
                        targets: '_all'
                    },
                    {
                        type: 'date',
                        targets: [2]

                    },
                ],
                language: {
                    emptyTable: "No messages have been sent/received from the selected user."
                },
                pagingType: "full_numbers",
                columns: [
                    {
                        data: 'direction'
                    },
                    {
                        data: null,
                        render: function (data) {
                            let body_json = JSON.parse(data.json);
                            return body_json.Body;
                        } 
                    },
                    {
                        data: 'ts',
                        className: 'text-center'
                    }
                ],
            });
        }

        $(document).ready( function () {
            $.ajax({
                url : '/participants/all',
                type : 'GET',

                success : function(data) {
                    if (data.success) {
                        data.participants.forEach(function(currentValue, index, arr){
                            // parse json;
                            let participant = JSON.parse(currentValue.json);
                            $('#select-participant-message').append("<option value='"+currentValue.uuid+"'>"+ participant.first_name + " "+ participant.last_name +"</option>");
                        });
                        $('#select-participant-message').selectpicker('refresh');
                        updateDT();
                        updateTimeZone();
                    } else {
                        showError(data.error_message);
                    }
                },
                error : function(request,error) {
                    console.error(error);
                }
            });
        } );

        $('#select-participant-message').on('changed.bs.select', function (e) {
            $('#collection').DataTable().destroy();
            updateDT();
            updateTimeZone();
        });

        function updateTimeZone() {
            let uuid = $('#select-participant-message').val();
            $.ajax({
                url : '/participants/get-time-zone',
                type : 'GET',
                data: {'uuid': uuid},
                success : function(data) {
                    if (data.success) {
                        $('#time-zone').html("Time Zone: " + data.time_zone);
                    } else {
                        $('#time-zone').html("Time Zone: Unknown");
                        // showError(data.error_message);
                        console.error(data.error_message);
                    }
                },
                error : function(request,error) {
                    console.error(error);
                }
            });
        }
    </script>
<?php
include_once __DIR__ . '/../_footer.php';
