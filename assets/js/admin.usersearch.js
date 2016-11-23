var _AUTH_USERSEARCH;
_AUTH_USERSEARCH = function()
{
    var base = this;

    // --------------------------------------------------------------------------

    /**
     * Construct _AUTH_USERSEARCH
     * @return {this}
     */
    base.__construct = function() {


        //  Set up the searchers
        var searchers = [
            {
                'class': 'user-search',
                'placeholder': 'Search for a user',
                'minimumInputLength': 3,
                'apiUrl': window.SITE_URL + 'api/auth/user/search',
                'format': function(data) {
                    return data.first_name + ' ' + data.last_name + ' (' + data.email + ')';
                }
            }
        ];

        for (var key in searchers) {
            if (searchers.hasOwnProperty(key)) {
                base.setupSearcher(searchers[key]);
            }
        }

        return base;

    };

    // --------------------------------------------------------------------------

    /**
     * Set up a searcher
     * @param  {Object} config The searcher's config
     * @return {Void}
     */
    base.setupSearcher = function(config) {
        $('input.' + config.class)
            .each(function() {
                var isMultiple = $(this).data('multiple') || false;
                $(this)
                    .select2({
                        placeholder: config.placeholder,
                        minimumInputLength: config.minimumInputLength,
                        multiple: isMultiple,
                        ajax: {
                            url: config.apiUrl + '/search',
                            dataType: 'json',
                            quietMillis: 250,
                            data: function (term) {
                                return {
                                    keywords: term
                                };
                            },
                            results: function (data) {

                                var out = {
                                    'results': []
                                };
                                for (var key in data.data) {
                                    if (data.data.hasOwnProperty(key)) {
                                        out.results.push({
                                            'id': data.data[key].id,
                                            'text': data.data[key].label
                                        });
                                    }
                                }
                                return out;
                            },
                            cache: true
                        },
                        initSelection: function(element, callback) {
                            var id = $(element).val();
                            if (id !== '') {

                                if (isMultiple) {

                                    $.ajax({
                                        url: config.apiUrl + '/id?ids=' + id,
                                        dataType: 'json'
                                    }).done(function(data) {
                                        var out = [];
                                        var text = '';
                                        for (var i = 0; i < data.data.length; i++) {

                                            if (typeof config.format === 'function') {
                                                text = config.format(data.data[i]);
                                            } else {
                                                text = data.data[i].label;
                                            }

                                            out.push({
                                                'id': data.data[i].id,
                                                'text': data.data[i].label
                                            });
                                        }
                                        callback(out);
                                    });

                                } else {

                                    $.ajax({
                                        url: config.apiUrl + '/id?id=' + id,
                                        dataType: 'json'
                                    }).done(function(data) {

                                        var text;

                                        if (typeof config.format === 'function') {
                                            text = config.format(data.data);
                                        } else {
                                            text = data.data.label;
                                        }

                                        callback({
                                            'id': data.data.id,
                                            'text': text
                                        });
                                    });
                                }
                            }
                        }
                    });
            });
    };

    // --------------------------------------------------------------------------

    return base.__construct();
}();
