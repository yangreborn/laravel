
<p style="font-size: 15px;line-height: 1.5em;text-align: left;">您好,</p>
@if(array_key_exists('bug', $data))
    @include('emails.notifications.tapd_bug_process', ['data' => $data['bug']])
@endif
@if(array_key_exists('overdue_bug', $data))
    @include('emails.notifications.tapd_bug_over_due', ['data' => $data['overdue_bug']])
@endif
@if(array_key_exists('bug_1', $data))
    @include('emails.notifications.tapd_bug_process', ['data' => $data['bug_1']])
@endif
@if(array_key_exists('story', $data))
    @include('emails.notifications.tapd_story_process', ['data' => $data['story']])
@endif
@if(array_key_exists('overdue_story', $data))
    @include('emails.notifications.tapd_story_over_due', ['data' => $data['overdue_story']])
@endif
@if(array_key_exists('task', $data))
    @include('emails.notifications.tapd_task_process', ['data' => $data['task']])
@endif
