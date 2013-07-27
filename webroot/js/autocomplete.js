(function($) {

	$('input.autocomplete').each(function() {
		var _this = $(this),
			cache = {},
			url = _this.data('url'),
			update = _this.data('linked-to');

		if (update) {
			update = $('#' + update);
		}

		_this.autocomplete({
			minLength: 0,
			select: function( event, ui ) {
				_this.val(ui.item ? ui.item.value : this.value);

				if (update) {
					update.val(ui.item ? ui.item.id : '');
				}
			},
			source: function( request, response) {
				var term = request.term;

				if (cache.hasOwnProperty(term)) {
					response(cache[term]);
					return;
				}

				$.getJSON(url, request, function(data, status, xhr) {
					cache[term] = data.data;
					response(data.data);
				});
			}
		}).focus(function() {
			_this.autocomplete('search');
		});
	});

}(jQuery));
