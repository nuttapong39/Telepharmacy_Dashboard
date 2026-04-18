#!/usr/bin/env python3
"""
convert_excel.py — แปลงไฟล์ Excel telepharmacy → JSON
ใช้งาน: python3 convert_excel.py <input.xlsx> <output.json>
"""
import sys, json, pandas as pd

if len(sys.argv) < 3:
    print("Usage: convert_excel.py <input.xlsx> <output.json>", file=sys.stderr)
    sys.exit(1)

xlsx_path = sys.argv[1]
json_path = sys.argv[2]

try:
    df = pd.read_excel(xlsx_path)
    df.columns = df.columns.str.strip()

    # Map flexible column names
    col_map = {
        'ลำดับ':                        'id',
        'วันที่รับบริการ':              'service_date',
        'HN':                           'hn',
        'ผลการดำเนินการ telepharmacy':  'telepharmacy',
        'Medication error':             'medication_error',
        'Medication error ':            'medication_error',
    }
    df.rename(columns={k:v for k,v in col_map.items() if k in df.columns}, inplace=True)

    required = ['id','service_date','hn','telepharmacy','medication_error']
    for c in required:
        if c not in df.columns:
            print(f"ERROR: ไม่พบคอลัมน์ '{c}'", file=sys.stderr)
            sys.exit(1)

    df['service_date'] = pd.to_datetime(df['service_date']).dt.strftime('%Y-%m-%d')
    df['hn']           = df['hn'].astype(str).str.strip()
    df['telepharmacy'] = df['telepharmacy'].astype(str).str.strip()
    df['medication_error'] = df['medication_error'].astype(str).str.strip()
    df['month'] = pd.to_datetime(df['service_date']).dt.to_period('M')

    records = df[['id','service_date','hn','telepharmacy','medication_error']].to_dict('records')
    for r in records:
        r['id'] = int(r['id']) if str(r['id']).isdigit() else 0

    monthly_df = df.groupby('month').agg(
        total=('hn','count'),
        not_miss=('telepharmacy',    lambda x:(x=='ไม่ขาดยา').sum()),
        miss_1day=('telepharmacy',   lambda x:(x=='ขาดยา 1 วัน').sum()),
        no_followup=('telepharmacy', lambda x:(x=='ติดตามไม่ได้').sum()),
        med_error=('medication_error',lambda x:(x=='พบ').sum()),
    ).reset_index()
    monthly = [
        {
            'month': str(r['month']),
            'total': int(r['total']),
            'not_miss': int(r['not_miss']),
            'miss_1day': int(r['miss_1day']),
            'no_followup': int(r['no_followup']),
            'med_error': int(r['med_error']),
        }
        for _, r in monthly_df.iterrows()
    ]

    output = {'records': records, 'monthly': monthly}
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    print(f"OK — {len(records)} records, {len(monthly)} months")

except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
