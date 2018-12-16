class SearchUser {

    /**
     * Construct SearchUser
     */
    constructor() {
        //  Set up the searchers
        let searchers = [
            {
                'class': 'user-search',
                'placeholder': 'Search for a user',
                'minimumInputLength': 3,
                'apiUrl': window.SITE_URL + 'api/auth/user',
                'format': function(data) {
                    return '#' + data.id + ' - ' + data.first_name + ' ' + data.last_name + ' (' + data.email + ')';
                }
            }
        ];

        for (let key in searchers) {
            if (searchers.hasOwnProperty(key)) {
                this.setupSearcher(searchers[key]);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Set up a searcher
     * @param  {Object} config The searcher's config
     * @return {Void}
     */
    setupSearcher(config) {

        $('input.' + config.class)
            .each((index, element) => {

                let $element = $(element);
                let isMultiple = $element.data('multiple') || false;
                $element
                    .select2({
                        placeholder: config.placeholder,
                        minimumInputLength: config.minimumInputLength,
                        multiple: isMultiple,
                        ajax: {
                            url: config.apiUrl + '/search',
                            dataType: 'json',
                            quietMillis: 250,
                            data: function(term) {
                                return {
                                    keywords: term
                                };
                            },
                            results: function(data) {
                                let text;
                                let out = {
                                    'results': []
                                };
                                for (let key in data.data) {
                                    if (data.data.hasOwnProperty(key)) {
                                        if (typeof config.format === 'function') {
                                            text = config.format(data.data[key]);
                                        } else {
                                            text = data.data[key].label;
                                        }

                                        out.results.push({
                                            'id': data.data[key].id,
                                            'text': text
                                        });
                                    }
                                }
                                return out;
                            },
                            cache: true
                        },
                        initSelection: function(element, callback) {
                            let id = $(element).val();
                            if (id !== '') {

                                if (isMultiple) {

                                    $.ajax({
                                        url: config.apiUrl + '/id?ids=' + id,
                                        dataType: 'json'
                                    })
                                        .done(function(data) {
                                            let out = [];
                                            let text = '';
                                            for (let i = 0; i < data.data.length; i++) {

                                                if (typeof config.format === 'function') {
                                                    text = config.format(data.data[i]);
                                                } else {
                                                    text = data.data[i].label;
                                                }

                                                out.push({
                                                    'id': data.data[i].id,
                                                    'text': text
                                                });
                                            }
                                            callback(out);
                                        });

                                } else {

                                    $.ajax({
                                        url: config.apiUrl + '/id?id=' + id,
                                        dataType: 'json'
                                    })
                                        .done(function(data) {

                                            let text;

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
    }
}

export default SearchUser;
