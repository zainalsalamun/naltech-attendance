Map<String, dynamic> leavePreview({int days = 1}) => {
      'period_year': 2026,
      'calculation': {
        'working_days_count': days,
        'total_calendar_days': days,
        'skipped_days_count': 0,
        'calendar_complete': true,
        'dates_breakdown': [],
        'unconfigured_dates': []
      },
      'balance': {
        'period_year': 2026,
        'allocated_days': 12,
        'reserved_days': 2,
        'used_days': 1,
        'remaining_days': 9,
        'remaining_after': 9 - days,
        'sufficient': true
      }
    };
