<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد الإلكتروني - تمليك</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Cairo, Arial, sans-serif;
            direction: rtl !important;
            text-align: right !important;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 30px 15px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            direction: rtl !important;
            text-align: right !important;
        }
        .header {
            background: linear-gradient(135deg, #134e4a 0%, #207C79 100%);
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
            direction: rtl !important;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            margin-top: 10px;
            color: #fef08a;
        }
        .content {
            padding: 32px 28px;
            direction: rtl !important;
            text-align: right !important;
            line-height: 1.7;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .message {
            font-size: 14px;
            color: #475569;
            margin-bottom: 24px;
        }
        .btn-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background: #207C79;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 700;
            padding: 14px 36px;
            text-decoration: none;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(32, 124, 121, 0.35);
            transition: background 0.3s ease;
        }
        .sub-message {
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 18px;
            margin-top: 24px;
        }
        .footer {
            background: #f8fafc;
            padding: 20px 28px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
            direction: rtl !important;
            text-align: right !important;
            line-height: 1.6;
        }
        .link-text {
            word-break: break-all;
            color: #207C79;
            direction: ltr !important;
            text-align: left !important;
            display: block;
            margin-top: 8px;
            font-size: 11px;
            background: #ffffff;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .copyright {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>منصة تمليك العقارية</h1>
                <div class="badge">تأكيد البريد الإلكتروني</div>
            </div>

            <!-- Content -->
            <div class="content">
                <div class="greeting">
                    مرحباً بك {{ !empty($user->name) ? $user->name : '' }}!
                </div>
                <div class="message">
                    شكراً لانضمامك إلى منصة <strong>تمليك</strong>. يرجى الضغط على الزر أدناه لتأكيد بريدك الإلكتروني وتفعيل حسابك للبدء في استخدام كافة خدمات المنصة:
                </div>

                <!-- Action Button -->
                <div class="btn-wrapper">
                    <a href="{{ $url }}" class="btn" target="_blank">
                        تأكيد البريد الإلكتروني
                    </a>
                </div>

                <div class="sub-message">
                    إذا لم تقم بإنشاء هذا الحساب، فلا داعي لاتخاذ أي إجراء ويمكنك تجاهل هذه الرسالة بأمان.
                    <br><br>
                    مع خالص التحيات،<br>
                    <strong>فريق عمل تمليك</strong>
                </div>
            </div>

            <!-- Footer for link fallback -->
            <div class="footer">
                إذا كنت تواجه مشكلة في النقر على زر «تأكيد البريد الإلكتروني»، يمكنك نسخ الرابط التالي ولصقه في متصفح الويب الخاص بك:
                <span class="link-text">{{ $url }}</span>
                <div class="copyright">
                    جميع الحقوق محفوظة © {{ date('Y') }} شركة تمليك للوساطة العقارية.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
