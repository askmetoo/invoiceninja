@extends('header')

@section('head')
    @parent

    @include('money_script')
    @include('proposals.grapesjs_header')

@stop

@section('content')

    {!! Former::open($url)
            ->method($method)
            ->onsubmit('return onFormSubmit(event)')
            ->addClass('warn-on-exit')
            ->rules([
                'invoice_id' => 'required',
            ]) !!}

    @if ($proposal)
        {!! Former::populate($proposal) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('html') !!}
        {!! Former::text('css') !!}
    </span>

    <div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        {!! Former::select('invoice_id')->addOption('', '')
                                ->label(trans('texts.quote'))
                                ->addGroupClass('invoice-select') !!}
                        {!! Former::select('proposal_template_id')->addOption('', '')
                                ->label(trans('texts.template'))
                                ->addGroupClass('template-select') !!}

                    </div>
                    <div class="col-md-6">
                        {!! Former::textarea('private_notes')
                                ->style('height: 100px') !!}
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))
                ->appendIcon(Icon::create('remove-circle'))
                ->asLinkTo(HTMLUtils::previousUrl('/proposals')) !!}

        {!! Button::success(trans("texts.save"))
                ->submit()
                ->appendIcon(Icon::create('floppy-disk')) !!}

        @if ($proposal)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                    ->withContents($proposal->present()->moreActions()) !!}
        @endif

    </center>

    {!! Former::close() !!}

    <div id="gjs"></div>

    <script type="text/javascript">
    var invoices = {!! $invoices !!};
    var invoiceMap = {};

    var templates = {!! $templates !!};
    var templateMap = {};

    function onFormSubmit() {
        $('#html').val(grapesjsEditor.getHtml());
        $('#css').val(grapesjsEditor.getCss());

        return true;
    }

    function loadTemplate() {
        var templateId = $('select#proposal_template_id').val();
        var template = templateMap[templateId];

        if (! template) {
            return;
        }

        var html = mergeTemplate(template.html);

        // grapesjsEditor.CssComposer.getAll().reset();
        grapesjsEditor.setComponents(html);
        grapesjsEditor.setStyle(template.css);
    }

    function mergeTemplate(html) {
        var invoiceId = $('select#invoice_id').val();
        var invoice = invoiceMap[invoiceId];

        if (!invoice) {
            return html;
        }

        invoice.account = {!! auth()->user()->account->load('country') !!};

        var regExp = new RegExp(/\$[a-z][\w\.]*/, 'g');
        var matches = html.match(regExp);

        if (matches) {
            for (var i=0; i<matches.length; i++) {
                var match = matches[i];

                field = match.replace('$quote.', '$');
                field = field.substring(1, field.length);
                field = toSnakeCase(field);

                if (field == 'quote_number') {
                    field = 'invoice_number';
                } else if (field == 'valid_until') {
                    field = 'due_date';
                } else if (field == 'quote_date') {
                    field = 'invoice_date';
                } else if (field == 'footer') {
                    field = 'invoice_footer';
                } else if (match == '$account.phone') {
                    field = 'account.work_phone';
                } else if (match == '$client.phone') {
                    field = 'client.phone';
                }

                var value = getDescendantProp(invoice, field) || ' ';
                value = doubleDollarSign(value) + '';
                value = value.replace(/\n/g, "\\n").replace(/\r/g, "\\r");

                if (['amount', 'partial', 'client.balance', 'client.paid_to_date'].indexOf(field) >= 0) {
                    value = formatMoneyInvoice(value, invoice);
                } else if (['invoice_date', 'due_date', 'partial_due_date'].indexOf(field) >= 0) {
                    value = moment.utc(value).format('{{ $account->getMomentDateFormat() }}');
                }

                html = html.replace(match, value);
            }
        }

        return html;
    }

    @if ($proposal)
        function onArchiveClick() {
            submitForm_proposal('archive', {{ $proposal->id }});
    	}

    	function onDeleteClick() {
            sweetConfirm(function() {
                submitForm_proposal('delete', {{ $proposal->id }});
            });
    	}
    @endif
    
    $(function() {
        var invoiceId = {{ ! empty($invoicePublicId) ? $invoicePublicId : 0 }};
        var $invoiceSelect = $('select#invoice_id');
        for (var i = 0; i < invoices.length; i++) {
            var invoice = invoices[i];
            invoiceMap[invoice.public_id] = invoice;
            $invoiceSelect.append(new Option(invoice.invoice_number + ' - ' + getClientDisplayName(invoice.client), invoice.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_INVOICE])
        if (invoiceId) {
            var invoice = invoiceMap[invoiceId];
            if (invoice) {
                $invoiceSelect.val(invoice.public_id);
                setComboboxValue($('.invoice-select'), invoice.public_id, invoice.invoice_number + ' - ' + getClientDisplayName(invoice.client));
            }
        }
        $invoiceSelect.change(loadTemplate);

        var templateId = {{ ! empty($templatePublicId) ? $templatePublicId : 0 }};
        var $proposal_templateSelect = $('select#proposal_template_id');
        for (var i = 0; i < templates.length; i++) {
            var template = templates[i];
            templateMap[template.public_id] = template;
            $proposal_templateSelect.append(new Option(template.name, template.public_id));
        }
        @include('partials/entity_combobox', ['entityType' => ENTITY_PROPOSAL_TEMPLATE])
        if (templateId) {
            var template = templateMap[templateId];
            $proposal_templateSelect.val(template.public_id);
            setComboboxValue($('.template-select'), template.public_id, template.name);
        }
        $proposal_templateSelect.change(loadTemplate);
	})

    </script>

    @include('partials.bulk_form', ['entityType' => ENTITY_PROPOSAL])
    @include('proposals.grapesjs', ['entity' => $proposal])

    <script type="text/javascript">

    $(function() {
        grapesjsEditor.on('canvas:drop', function() {
            var html = mergeTemplate(grapesjsEditor.getHtml());
            grapesjsEditor.setComponents(html);
        });

        @if (! $proposal && $templatePublicId)
            loadTemplate();
        @endif
    });

    </script>

@stop
