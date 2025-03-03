<?php
/** @var User $user */
$page = 'messages-index';
include_once __DIR__ . '/../_header.php';
global $rootURL;
?>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h4 mr-auto p-2">Messages - <span class="text-muted">Overview</span></h1>
        <div class="float-right">
            <a href="<?= $rootURL; ?>/messages/export?study=Default" class="btn btn-secondary p-2 mr-2">
                <i class="fas fa-download"></i>
                Export Messages
            </a>
            <button type="button" class="btn btn-success p-2" data-bs-toggle="modal" data-bs-target="#messageModal">
                <i class="fas fa-paper-plane"></i>
                Send Message
            </button>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <table id="collection" class="table table-bordered dt-responsive responsive-text" style="width:100%">
                <thead>
                <tr>
                    <th>Outgoing/Incoming</th>
                    <th>Participant Name</th>
                    <th>Message Body</th>
                    <th>Timestamp</th>
                    <th>Time Zone</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                <tr>
                    <th>Outgoing/Incoming</th>
                    <th>Participant Name</th>
                    <th>Message Body</th>
                    <th>Timestamp</th>
                    <th>Time Zone</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Submit message modal -->  
    <div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Send Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-12 mb-3 gx-1 form-floating">
                            <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                <select class="selectpicker" data-width="100%" id="messageModalParticipant" data-none-selected-text="Select Participants" multiple data-live-search="true" data-live-search-placeholder="Search"></select>
                                <label id="messageModalParticipant"></label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 mb-3 gx-1 form-floating">
                            <textarea class="form-control" style="pointer-events: auto;" id="messageModalMessage" placeholder="Message..."></textarea>
                            <label for="messageModalMessage">Message</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <input type="checkbox" id="nowCheckbox" checked />
                            <label for="nowCheckbox">Now</label>
                        </div>
                        <div class="col-md-8">
                            <input type="checkbox" id="scheduledCheckbox"/>
                            <label for="scheduledCheckbox">Scheduled: </label>
                            <input type="datetime-local" id="scheduledDateTime" />

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button id="send-button" type="button" class="btn btn-primary" onclick="send_message();">Send Message</button>
                </div>
            </div>
        </div>
    </div>
   
    <script type="text/javascript">
        let messageModal = $('#messageModal');
        let messageModalParticipant = $('#messageModalParticipant');
        let messageModalMessage = $('#messageModalMessage');

        let collection = {};
        let collectionTable = $('#collection');
        let collectionDataTable = null;

        $(function() {
            collectionDataTable = collectionTable.DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "/messages/list",
                    data: {'study': 'Default'}
                },
                order: [[ 3, "desc" ]],
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    'pageLength', 'colvis'
                ],
                columnDefs: [
                    {
                        className: "dt-center",
                        targets: '_all'
                    },
                    {
                        orderable: true,
                        targets: '_all'
                    },
                    {
                        type: "date",
                        targets: [3]
                    }
                ],
                language: {
                    emptyTable: "No messages have been sent/received yet."
                },
                pagingType: "full_numbers",
                columns: [
                    {
                        data: 'direction'
                    },
                    {
                        data: 'participant_name'
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
                    },
                    {
                        data: 'time_zone',
                    }
                ]
            });

            collectionDataTable.buttons().container().prependTo('#collection_filter');
            collectionDataTable.buttons().container().addClass('float-left');
            $('.dt-buttons').addClass('btn-group-sm');
            $('.dt-buttons div').addClass('btn-group-sm');
            collectionTable.on('xhr.dt', function (e, settings, data) {
                messages = {};
                $.each(data.data, function(i, v) {
                    messages[v.uuid] = v;
                });
            });

        });

        messageModal.on('hidden.bs.modal', function() {
            clear_message_form();
        });


        function clear_message_form() {
            messageModalParticipant.val('default');
            messageModalParticipant.selectpicker('refresh');
            messageModalMessage.val('');
        }

        $(document).ready( function () {
            // ajax to get Participant Name (Phone number)
            $.ajax({
                url: '/participants/all',
                type: 'GET',
                success: function(data) {
                    if (data.success) {
                        for(const participant of data.participants) {
                            part_json = JSON.parse(participant.json);
                            messageModalParticipant.append('<option value="' + participant.uuid + '">' + part_json['first_name']+' '+part_json['last_name']+ ' (' + part_json['number']+ ')</option>');
                        }
                        messageModalParticipant.selectpicker('refresh');
                    } else {
                        showError(data.error_message);
                        return null;
                    }
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                    console.error(status);
                    console.error(error);
                    showError("Error communicating with the server.");
                    return null;
                }
            });
        });

        function send_message(){
            let scheduled_time = -1;
            if ($("#scheduledCheckbox").prop("checked")){
                scheduled_time = $("#scheduledDateTime").val();
            }
            //  format 2023-10-30T10:14

            // ajax to send message
            $.ajax({
                url: '/messages/send',
                type: 'POST',
                data: {
                    'participant_uuid': messageModalParticipant.val(),
                    'body': messageModalMessage.val(),
                    'study': 'Default',
                    'time_to_send': scheduled_time
                },
                success: function(data) {
                    if (data.success) {
                        if ($("#scheduledCheckbox").prop("checked")){
                            showSuccess('Message scheduled successfully.');
                        } else {
                            showSuccess('Message sent successfully.');
                        }
                        messageModal.modal('hide');
                        collectionDataTable.ajax.reload();
                        collectionScheduledDataTable.ajax.reload();
                    } else {
                        showError(data.error_message);
                        return null;
                    }
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                    console.error(status);
                    console.error(error);
                    showError("Error communicating with the server.");
                    return null;
                }
            });
        }
    </script>
<?php
include_once __DIR__ . '/../_footer.php';
