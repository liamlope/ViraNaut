(function () {
    if (!window.mirzaBotTools) return;
    var topics = {};
    window.mirzaBotTools.get('topics_list').then(function (d) {
        if (!d.ok) return;
        document.getElementById('channelReport').value = d.channel_report || '';
        var html = '';
        d.items.forEach(function (t) {
            topics[t.report] = t.idreport;
            html += '<label class="field"><span class="field-label">' + t.report + '</span>' +
                '<input type="text" class="input topic-inp" data-report="' + t.report + '" value="' + (t.idreport || '') + '" dir="ltr"></label>';
        });
        document.getElementById('topicsForm').innerHTML = html;
    });
    document.getElementById('saveTopics').onclick = function () {
        var data = {};
        document.querySelectorAll('.topic-inp').forEach(function (inp) {
            data[inp.getAttribute('data-report')] = inp.value;
        });
        window.mirzaBotTools.post('topics_save', {
            channel_report: document.getElementById('channelReport').value,
            topics: JSON.stringify(data),
        }).then(function (r) {
            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
        });
    };
}());
