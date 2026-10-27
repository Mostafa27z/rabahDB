<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $eventTitle }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            direction: rtl !important;
            text-align: right !important;
            color: #1e293b;
        }
        .container {
            max-width: 620px;
            margin: 25px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            direction: rtl !important;
            text-align: right !important;
        }
        .header {
            background: linear-gradient(135deg, #134e4a 0%, #207C79 100%);
            color: #ffffff;
            padding: 24px 28px;
            text-align: center;
            direction: rtl !important;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            margin-top: 10px;
            color: #fef08a;
        }
        .content {
            padding: 24px 28px;
            direction: rtl !important;
            text-align: right !important;
        }
        .property-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 14px 0;
            line-height: 1.4;
            text-align: right !important;
            direction: rtl !important;
        }
        .property-image {
            width: 100%;
            height: 240px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            background-color: #f1f5f9;
        }
        .info-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-bottom: 20px;
            direction: rtl !important;
            text-align: right !important;
        }
        .info-grid td {
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
            direction: rtl !important;
            text-align: right !important;
        }
        .info-label {
            font-weight: 700;
            color: #64748b;
            width: 34%;
            direction: rtl !important;
            text-align: right !important;
        }
        .info-value {
            font-weight: 800;
            color: #0f172a;
            direction: rtl !important;
            text-align: right !important;
        }
        .price-highlight {
            color: #0d9488;
            font-size: 16px;
            font-weight: 900;
        }
        .price-change {
            background: #fef3c7;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #fde68a;
            color: #92400e;
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 18px;
            text-align: right;
            direction: rtl;
        }
        .btn-container {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            direction: rtl;
        }
        .btn {
            display: inline-block;
            background: #207C79;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(32, 124, 121, 0.3);
            margin: 0 4px;
        }
        .btn-map {
            background: #0284c7;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);
        }
        .footer {
            background: #f8fafc;
            padding: 16px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            direction: rtl;
        }
    </style>
</head>
<body dir="rtl" style="margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; text-align: right;">
    <div class="container" dir="rtl" style="max-width: 620px; margin: 25px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; direction: rtl; text-align: right;">
        <!-- Header -->
        <div class="header" dir="rtl" style="background: linear-gradient(135deg, #134e4a 0%, #207C79 100%); color: #ffffff; padding: 24px 28px; text-align: center; direction: rtl;">
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">منظومة عقارات رباح</h1>
            <div class="badge" style="display: inline-block; background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.35); padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; margin-top: 10px; color: #fef08a;">{{ $eventTitle }}</div>
        </div>

        <!-- Content -->
        <div class="content" dir="rtl" style="padding: 24px 28px; direction: rtl; text-align: right;">
            <h2 class="property-title" dir="rtl" style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; line-height: 1.4; text-align: right; direction: rtl;">{{ $property->title }}</h2>

            @php
                $rawCover = $property->cover_image;
                if (!$rawCover && is_array($property->images) && count($property->images) > 0) {
                    $rawCover = $property->images[0];
                }
                $coverUrl = null;
                if ($rawCover) {
                    if (str_starts_with($rawCover, 'http://') || str_starts_with($rawCover, 'https://')) {
                        $coverUrl = $rawCover;
                    } else {
                        $base = rtrim(config('app.url', 'http://localhost:8000'), '/');
                        $coverUrl = $base . '/storage/' . ltrim($rawCover, '/');
                    }
                }
                $agent = $property->owner ?: $property->creator;
            @endphp

            @if($coverUrl)
                <div style="text-align: center; margin-bottom: 20px;" dir="rtl">
                    <img src="{{ $coverUrl }}" alt="{{ $property->title }}" class="property-image" style="width: 100%; max-height: 280px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; display: block;">
                </div>
            @endif

            @if($performedByName)
                <div dir="rtl" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #166534; font-weight: 700; direction: rtl; text-align: right;">
                    👤 قام بهذا الإجراء: <strong>{{ $performedByName }}</strong>
                </div>
            @endif

            @if($eventType === 'deleted')
                <div dir="rtl" style="background: #fef2f2; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #991b1b; font-weight: 800; direction: rtl; text-align: right;">
                    ⚠️ تنبيه: تم حذف هذا العقار من المنظومة بواسطة <strong>{{ $performedByName }}</strong>
                </div>
            @endif

            @if($eventType === 'price_changed' && $oldPrice && $newPrice)
                <div class="price-change" dir="rtl" style="background: #fef3c7; padding: 10px 14px; border-radius: 8px; border: 1px solid #fde68a; color: #92400e; font-weight: 800; font-size: 13px; margin-bottom: 18px; direction: rtl; text-align: right;">
                    💰 تم تعديل السعر من <strong>{{ number_format($oldPrice) }} د.إ</strong> إلى <strong style="color: #047857;">{{ number_format($newPrice) }} د.إ</strong>
                </div>
            @endif

            <table class="info-grid" dir="rtl" align="right" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-bottom: 20px; direction: rtl; text-align: right;">
                <tbody dir="rtl" style="direction: rtl;">
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;منفّذ الإجراء:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;"><strong style="color: #0d9488;">{{ $performedByName ?: 'أحد أعضاء الفريق' }}</strong></td>
                    </tr>
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;رقم المعرف المرجعي:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">{{ $property->reference ?: ('Shj-' . $property->id) }}</td>
                    </tr>
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;الأولوية:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">
                            @if($property->priority === 'high' || $property->priority === 'عالية')
                                <span style="color: #dc2626;">🔥 أولوية عالية</span>
                            @elseif($property->priority === 'medium' || $property->priority === 'متوسطة')
                                <span style="color: #d97706;">⚡ أولوية متوسطة</span>
                            @else
                                <span style="color: #475569;">عادية</span>
                            @endif
                        </td>
                    </tr>
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;السعر:&rlm;</td>
                        <td class="info-value price-highlight" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 16px; font-weight: 900; color: #0d9488; text-align: right; direction: rtl; vertical-align: middle;">{{ number_format((float)($property->price ?: 0)) }} {{ $property->currency ?: 'AED' }}</td>
                    </tr>
                    @if($property->property_type)
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;نوع العقار:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">{{ $property->property_type }}</td>
                    </tr>
                    @endif
                    @if($property->area_sqft)
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;المساحة:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">{{ number_format((float)$property->area_sqft) }} قدم مربع</td>
                    </tr>
                    @endif
                    @if($property->city || $property->area_name)
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;الموقع / المنطقة:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">{{ $property->city ? $property->city . ' - ' : '' }}{{ $property->area_name }}</td>
                    </tr>
                    @endif
                    @if($property->formatted_details)
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: top;">&rlm;تفاصيل العقار (موقع الشركة):&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; white-space: pre-line; line-height: 1.6; text-align: right; direction: rtl; vertical-align: top;">{{ $property->formatted_details }}</td>
                    </tr>
                    @endif
                    <tr dir="rtl" style="direction: rtl;">
                        <td class="info-label" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 700; color: #64748b; width: 34%; text-align: right; direction: rtl; vertical-align: middle;">&rlm;الوكيل المسؤول:&rlm;</td>
                        <td class="info-value" dir="rtl" align="right" style="padding: 10px 14px; background-color: #f8fafc; border-radius: 8px 0 0 8px; font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; direction: rtl; vertical-align: middle;">
                            <strong>{{ $agent ? $agent->name : 'إدارة رباح' }}</strong>
                            @if($agent && $agent->phone)
                                <br><span style="font-size: 11px; color: #64748b;">هاتف: {{ $agent->phone }}</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Actions -->
            <div class="btn-container" dir="rtl" style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; direction: rtl;">
                @if($property->google_map_url)
                    <a href="{{ $property->google_map_url }}" target="_blank" class="btn btn-map" style="display: inline-block; background: #0284c7; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 800; margin: 0 4px;">📍 موقع العقار على قوقل ماب</a>
                @endif
                @if($eventType !== 'deleted')
                    <a href="{{ config('app.frontend_url', 'https://rabah.eg') }}/dashboard/properties/{{ $property->id }}" target="_blank" class="btn" style="display: inline-block; background: #207C79; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 800; margin: 0 4px;">عرض تفاصيل العقار</a>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" dir="rtl" style="background: #f8fafc; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; direction: rtl;">
            تم إرسال هذا التنبيه التلقائي لفريق عمل وإدارة عقارات رباح.<br>
            © {{ date('Y') }} شركة رباح للتسويق العقاري. جميع الحقوق محفوظة.
        </div>
    </div>
</body>
</html>
