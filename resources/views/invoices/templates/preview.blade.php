@php
    $s = $settings ?? \App\Models\InvoiceTemplate::getDefaultSettings();
    $tenant = auth()->user()->tenant ?? null;
    $currencySymbol = $tenant?->currency_symbol ?? '$';
    $companyName = $tenant?->name ?? 'Your Company';
@endphp
<div style="background: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.1); font-family: {{ $s['font_family'] }}; font-size: {{ $s['font_size'] }}px; line-height: 1.6; color: #333;">
    {{-- Header --}}
    @if(($s['layout'] ?? 'classic') === 'modern')
    <div style="padding: 30px 40px; background: {{ $s['header_bg_color'] }}; color: {{ $s['header_text_color'] }}; display: flex; justify-content: space-between; align-items: flex-start;">
        <div style="display: flex; align-items: center; gap: 15px;">
            @if($s['show_logo'])
            <div style="width: 60px; height: 60px; background: {{ $s['primary_color'] }}; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                {{ strtoupper(substr($companyName, 0, 2)) }}
            </div>
            @endif
            <div>
                <h1 style="font-size: 22px; font-weight: 700; margin: 0 0 4px 0; color: {{ $s['header_text_color'] }};">{{ $companyName }}</h1>
                <p style="font-size: 11px; color: {{ $s['header_text_color'] }}; opacity: 0.8; margin: 0;">123 Business Street, City, Country</p>
                <p style="font-size: 11px; color: {{ $s['header_text_color'] }}; opacity: 0.8; margin: 0;">info@company.com | +1 234 567 890</p>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 28px; font-weight: 700; color: {{ $s['primary_color'] }}; letter-spacing: 1px; margin-bottom: 5px;">INVOICE</div>
            <div style="font-size: 14px; color: {{ $s['secondary_color'] }}; font-weight: 600;"># INV-000123</div>
            @if($s['show_status_badge'])
            <div style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 8px; background: #D9E4EF; color: #183E61;">Sent</div>
            @endif
        </div>
    </div>
    @elseif(($s['layout'] ?? 'classic') === 'minimal')
    <div style="padding: 40px 40px 20px 40px; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            @if($s['show_logo'])
            <h1 style="font-size: 24px; font-weight: 800; margin: 0 0 8px 0; color: {{ $s['primary_color'] }};">{{ $companyName }}</h1>
            @endif
            <p style="font-size: 11px; color: #6B7280; margin: 0;">123 Business Street, City, Country</p>
            <p style="font-size: 11px; color: #6B7280; margin: 0;">info@company.com | +1 234 567 890</p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px;">Invoice</div>
            <div style="font-size: 20px; font-weight: 700; color: {{ $s['primary_color'] }};">INV-000123</div>
            @if($s['show_status_badge'])
            <div style="display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; margin-top: 8px; border: 1px solid {{ $s['primary_color'] }}; color: {{ $s['primary_color'] }};">Sent</div>
            @endif
        </div>
    </div>
    <div style="margin: 0 40px; border-bottom: 1px solid #E5E7EB;"></div>
    @elseif(($s['layout'] ?? 'classic') === 'compact')
    <div style="padding: 20px 30px; border-bottom: {{ $s['border_width'] }}px {{ $s['border_style'] }} {{ $s['primary_color'] }}; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 10px;">
            @if($s['show_logo'])
            <div style="width: 40px; height: 40px; background: {{ $s['primary_color'] }}; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; font-weight: bold;">
                {{ strtoupper(substr($companyName, 0, 2)) }}
            </div>
            @endif
            <div>
                <h1 style="font-size: 16px; font-weight: 700; margin: 0; color: {{ $s['secondary_color'] }};">{{ $companyName }}</h1>
                <p style="font-size: 10px; color: #6B7280; margin: 0;">info@company.com | +1 234 567 890</p>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 20px; font-weight: 700; color: {{ $s['primary_color'] }}; margin-bottom: 2px;">INVOICE</div>
            <div style="font-size: 11px; color: #6B7280;"># INV-000123</div>
        </div>
    </div>
    @else
    {{-- Classic layout --}}
    <div style="padding: 30px 40px; border-bottom: {{ $s['border_width'] }}px {{ $s['border_style'] }} {{ $s['primary_color'] }}; background: {{ $s['header_bg_color'] }}; display: flex; justify-content: space-between; align-items: flex-start;">
        <div style="display: flex; align-items: center; gap: 15px;">
            @if($s['show_logo'])
            <div style="width: 70px; height: 70px; background: {{ $s['primary_color'] }}; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: bold;">
                {{ strtoupper(substr($companyName, 0, 2)) }}
            </div>
            @endif
            <div>
                <h1 style="font-size: 22px; color: {{ $s['header_text_color'] }}; font-weight: 700; margin: 0 0 4px 0;">{{ $companyName }}</h1>
                <p style="font-size: 11px; color: #6B7280; margin: 0; line-height: 1.5;">123 Business Street</p>
                <p style="font-size: 11px; color: #6B7280; margin: 0; line-height: 1.5;">City, State, Country</p>
                <p style="font-size: 11px; color: #6B7280; margin: 0; line-height: 1.5;">info@company.com</p>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 32px; font-weight: 700; color: {{ $s['primary_color'] }}; letter-spacing: 1px; margin-bottom: 5px;">INVOICE</div>
            <div style="font-size: 14px; color: #4B5563; font-weight: 600;"># INV-000123</div>
            @if($s['show_status_badge'])
            <div style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-top: 8px; background: #D9E4EF; color: #183E61;">Sent</div>
            @endif
        </div>
    </div>
    @endif

    {{-- Body --}}
    <div style="padding: {{ ($s['layout'] ?? 'classic') === 'compact' ? '20px 30px' : '30px 40px' }};">
        {{-- Bill To & Dates --}}
        <div style="display: flex; justify-content: space-between; margin-bottom: {{ ($s['layout'] ?? 'classic') === 'compact' ? '20px' : '30px' }}; gap: 30px;">
            <div style="flex: 1;">
                <h3 style="font-size: 10px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px 0; font-weight: 600;">Bill To</h3>
                <p style="font-size: 16px; font-weight: 600; color: {{ $s['secondary_color'] }}; margin: 0 0 4px 0;">John Smith</p>
                <p style="font-size: 12px; color: #6B7280; margin: 0 0 2px 0;">Acme Corporation</p>
                <p style="font-size: 12px; color: #6B7280; margin: 0 0 2px 0;">456 Customer Ave, Suite 100</p>
                <p style="font-size: 12px; color: #6B7280; margin: 0 0 2px 0;">john@acme.com</p>
            </div>
            <div style="background: {{ $s['table_header_bg'] }}; padding: 15px 20px; border-radius: 8px; min-width: 200px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; color: #6B7280;">Invoice Date:</span>
                    <span style="font-size: 12px; font-weight: 600; color: {{ $s['secondary_color'] }};">Apr 08, 2026</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; color: #6B7280;">Due Date:</span>
                    <span style="font-size: 12px; font-weight: 600; color: {{ $s['secondary_color'] }};">May 08, 2026</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 11px; color: #6B7280;">Reference:</span>
                    <span style="font-size: 12px; font-weight: 600; color: {{ $s['secondary_color'] }};">PO-2026-001</span>
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <thead>
                <tr style="background: {{ $s['table_header_bg'] }};">
                    <th style="padding: 12px 15px; text-align: left; font-size: 10px; font-weight: 600; color: {{ $s['table_header_text'] }}; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid {{ $s['table_border_color'] }}; width: {{ $s['show_tax_column'] ? '40%' : '50%' }};">Description</th>
                    <th style="padding: 12px 15px; text-align: right; font-size: 10px; font-weight: 600; color: {{ $s['table_header_text'] }}; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid {{ $s['table_border_color'] }}; width: 12%;">Qty</th>
                    <th style="padding: 12px 15px; text-align: right; font-size: 10px; font-weight: 600; color: {{ $s['table_header_text'] }}; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid {{ $s['table_border_color'] }}; width: 15%;">Unit Price</th>
                    @if($s['show_tax_column'])
                    <th style="padding: 12px 15px; text-align: right; font-size: 10px; font-weight: 600; color: {{ $s['table_header_text'] }}; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid {{ $s['table_border_color'] }}; width: 12%;">Tax</th>
                    @endif
                    <th style="padding: 12px 15px; text-align: right; font-size: 10px; font-weight: 600; color: {{ $s['table_header_text'] }}; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid {{ $s['table_border_color'] }}; width: 16%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid {{ $s['table_border_color'] }};">
                        <div style="font-weight: 500; color: {{ $s['secondary_color'] }};">Website Development</div>
                        <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">SKU: WEB-001</div>
                    </td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">1.00</td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}2,500.00</td>
                    @if($s['show_tax_column'])
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">15%</td>
                    @endif
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}2,875.00</td>
                </tr>
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid {{ $s['table_border_color'] }};">
                        <div style="font-weight: 500; color: {{ $s['secondary_color'] }};">Hosting Plan - Annual</div>
                        <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">SKU: HST-012</div>
                    </td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">1.00</td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}300.00</td>
                    @if($s['show_tax_column'])
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">15%</td>
                    @endif
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}345.00</td>
                </tr>
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid {{ $s['table_border_color'] }};">
                        <div style="font-weight: 500; color: {{ $s['secondary_color'] }};">Logo Design</div>
                    </td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">2.00</td>
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}150.00</td>
                    @if($s['show_tax_column'])
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">0%</td>
                    @endif
                    <td style="padding: 15px; text-align: right; border-bottom: 1px solid {{ $s['table_border_color'] }};">{{ $currencySymbol }}300.00</td>
                </tr>
            </tbody>
        </table>

        {{-- Totals --}}
        <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
            <div style="width: 280px; background: {{ $s['table_header_bg'] }}; border-radius: 8px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;">
                    <span style="color: #6B7280;">Subtotal</span>
                    <span style="font-weight: 500; color: {{ $s['secondary_color'] }};">{{ $currencySymbol }}2,950.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;">
                    <span style="color: #6B7280;">Tax</span>
                    <span style="font-weight: 500; color: {{ $s['secondary_color'] }};">{{ $currencySymbol }}420.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 2px solid {{ $s['table_border_color'] }}; margin-top: 15px; padding-top: 15px;">
                    <span style="font-size: 15px; font-weight: 600; color: {{ $s['secondary_color'] }};">Total</span>
                    <span style="font-size: 18px; font-weight: 700; color: {{ $s['primary_color'] }};">{{ $currencySymbol }}3,370.00</span>
                </div>
                @if($s['show_payment_info'])
                <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px;">
                    <span style="color: #6B7280;">Amount Paid</span>
                    <span style="font-weight: 500; color: {{ $s['accent_color'] }};">{{ $currencySymbol }}1,000.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px dashed {{ $s['table_border_color'] }};">
                    <span style="font-weight: 600; color: {{ $s['secondary_color'] }};">Balance Due</span>
                    <span style="font-weight: 700; color: #DC2626;">{{ $currencySymbol }}2,370.00</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Notes & Terms --}}
        @if($s['show_notes'] || $s['show_terms'])
        <div style="display: grid; grid-template-columns: {{ $s['show_notes'] && $s['show_terms'] ? '1fr 1fr' : '1fr' }}; gap: 30px; margin-bottom: 25px; padding-top: 20px; border-top: 1px solid {{ $s['table_border_color'] }};">
            @if($s['show_notes'])
            <div style="background: {{ $s['table_header_bg'] }}; padding: 15px; border-radius: 8px; border-left: 3px solid {{ $s['primary_color'] }};">
                <h4 style="font-size: 10px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 8px 0; font-weight: 600;">Notes</h4>
                <p style="font-size: 12px; color: #4B5563; margin: 0;">Payment received with thanks. Please retain this invoice for your records.</p>
            </div>
            @endif
            @if($s['show_terms'])
            <div style="background: {{ $s['table_header_bg'] }}; padding: 15px; border-radius: 8px; border-left: 3px solid {{ $s['primary_color'] }};">
                <h4 style="font-size: 10px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 8px 0; font-weight: 600;">Terms & Conditions</h4>
                <p style="font-size: 12px; color: #4B5563; margin: 0;">Payment is due within 30 days of the invoice date. Late payments may incur additional charges.</p>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- Footer --}}
    @if($s['show_footer'])
    <div style="background: {{ $s['footer_bg_color'] }}; padding: 25px 40px; border-top: 1px solid {{ $s['table_border_color'] }}; text-align: center;">
        <div style="font-size: 14px; color: {{ $s['primary_color'] }}; font-weight: 500; margin-bottom: 10px;">{{ $s['footer_text'] }}</div>
        <div style="font-weight: 600; color: {{ $s['secondary_color'] }}; margin-bottom: 5px;">{{ $companyName }}</div>
        <div style="font-size: 11px; color: {{ $s['footer_text_color'] }};">
            info@company.com&nbsp;|&nbsp;+1 234 567 890
        </div>
    </div>
    @endif
</div>
