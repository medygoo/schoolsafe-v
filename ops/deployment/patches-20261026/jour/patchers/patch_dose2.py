import io

# ---------- Calendar.php : 4 correctifs (E4/E5/E6) ----------
src = io.open(r'C:/Users/PC/AppData/Local/Temp/ecole-extraction/Calendar.php', encoding='utf-8').read()

# C1 — ligne 373 : DELETE jours par CALENDAR_ID sans garde (branche création)
old1 = '\t\t\t\tDBQuery( "DELETE FROM attendance_calendar\n\t\t\t\t\tWHERE CALENDAR_ID=\'" . (int) $calendar_id . "\'" );'
new1 = ('\t\t\t\t// SchoolSafe E4 : garde ecole+annee (miroir des suppressions voisines).\n'
        '\t\t\t\tDBQuery( "DELETE FROM attendance_calendar\n'
        '\t\t\t\t\tWHERE CALENDAR_ID=\'" . (int) $calendar_id . "\'\n'
        '\t\t\t\t\tAND SCHOOL_ID=\'" . UserSchool() . "\'\n'
        '\t\t\t\t\tAND SYEAR=\'" . UserSyear() . "\'" );')
assert src.count(old1) == 1, 'C1 motif: %d' % src.count(old1)
src = src.replace(old1, new1)

# C2 — lignes 415-419 : suppression de calendrier sans garde
old2 = ('\t\t$delete_sql = "DELETE FROM attendance_calendar\n'
        '\t\t\tWHERE CALENDAR_ID=\'" . (int) $_REQUEST[\'calendar_id\'] . "\';";\n'
        '\n'
        '\t\t$delete_sql .= "DELETE FROM attendance_calendars\n'
        '\t\t\tWHERE CALENDAR_ID=\'" . (int) $_REQUEST[\'calendar_id\'] . "\';";')
new2 = ('\t\t// SchoolSafe E4 : suppression limitee a l\'ecole et l\'annee courantes.\n'
        '\t\t$delete_sql = "DELETE FROM attendance_calendar\n'
        '\t\t\tWHERE CALENDAR_ID=\'" . (int) $_REQUEST[\'calendar_id\'] . "\'\n'
        '\t\t\tAND SCHOOL_ID=\'" . UserSchool() . "\'\n'
        '\t\t\tAND SYEAR=\'" . UserSyear() . "\';";\n'
        '\n'
        '\t\t$delete_sql .= "DELETE FROM attendance_calendars\n'
        '\t\t\tWHERE CALENDAR_ID=\'" . (int) $_REQUEST[\'calendar_id\'] . "\'\n'
        '\t\t\tAND SCHOOL_ID=\'" . UserSchool() . "\'\n'
        '\t\t\tAND SYEAR=\'" . UserSyear() . "\';";')
assert src.count(old2) == 1, 'C2 motif: %d' % src.count(old2)
src = src.replace(old2, new2)

# C3 — lignes 580-581 : suppression d'evenement sans garde
old3 = '\t\tDBQuery( "DELETE FROM calendar_events\n\t\t\tWHERE ID=\'" . (int) $_REQUEST[\'event_id\'] . "\'" );'
new3 = ('\t\t// SchoolSafe E4 : suppression limitee a l\'ecole et l\'annee courantes.\n'
        '\t\tDBQuery( "DELETE FROM calendar_events\n'
        '\t\t\tWHERE ID=\'" . (int) $_REQUEST[\'event_id\'] . "\'\n'
        '\t\t\tAND SYEAR=\'" . UserSyear() . "\'\n'
        '\t\t\tAND SCHOOL_ID=\'" . UserSchool() . "\'" );')
assert src.count(old3) == 1, 'C3 motif: %d' % src.count(old3)
src = src.replace(old3, new3)

# C4 — lignes 599-601 : lecture d'evenement inter-ecoles (BOLA)
old4 = ('\t\t\t$RET = DBGet( "SELECT TITLE,DESCRIPTION,SCHOOL_DATE\n'
        '\t\t\t\tFROM calendar_events\n'
        '\t\t\t\tWHERE ID=\'" . (int) $_REQUEST[\'event_id\'] . "\'" );')
new4 = ('\t\t\t// SchoolSafe E5 (BOLA) : lecture limitee a l\'ecole et l\'annee courantes\n'
        '\t\t\t// (miroir du correctif #379 sur les devoirs).\n'
        '\t\t\t$RET = DBGet( "SELECT TITLE,DESCRIPTION,SCHOOL_DATE\n'
        '\t\t\t\tFROM calendar_events\n'
        '\t\t\t\tWHERE ID=\'" . (int) $_REQUEST[\'event_id\'] . "\'\n'
        '\t\t\t\tAND SYEAR=\'" . UserSyear() . "\'\n'
        '\t\t\t\tAND SCHOOL_ID=\'" . UserSchool() . "\'" );')
assert src.count(old4) == 1, 'C4 motif: %d' % src.count(old4)
src = src.replace(old4, new4)

io.open(r'C:/Users/PC/AppData/Local/Temp/ecole-extraction/patched/Calendar.php', 'w', encoding='utf-8', newline='\n').write(src)
print('Calendar.php : 4 correctifs OK')

# ---------- MarkingPeriods.php : 1 correctif (E4) ----------
src2 = io.open(r'C:/Users/PC/AppData/Local/Temp/ecole-extraction/MarkingPeriods.php', encoding='utf-8').read()

old5 = ('\tif ( DeletePrompt( $name ) )\n'
        '\t{\n'
        '\t\tDBQuery( MarkingPeriodDeleteSQL( $_REQUEST[\'marking_period_id\'], $_REQUEST[\'mp_term\'] ) );')
new5 = ('\tif ( DeletePrompt( $name ) )\n'
        '\t{\n'
        '\t\t// SchoolSafe E4 : verifier que la periode appartient a l\'ecole et l\'annee\n'
        '\t\t// courantes avant suppression (miroir du controle de rendu plus bas).\n'
        '\t\t$marking_period_own_RET = DBGet( "SELECT MARKING_PERIOD_ID\n'
        '\t\t\tFROM school_marking_periods\n'
        '\t\t\tWHERE SCHOOL_ID=\'" . UserSchool() . "\'\n'
        '\t\t\tAND SYEAR=\'" . UserSyear() . "\'\n'
        '\t\t\tAND MARKING_PERIOD_ID=\'" . (int) $_REQUEST[\'marking_period_id\'] . "\'" );\n'
        '\n'
        '\t\tif ( $marking_period_own_RET )\n'
        '\t\t{\n'
        '\t\t\tDBQuery( MarkingPeriodDeleteSQL( $_REQUEST[\'marking_period_id\'], $_REQUEST[\'mp_term\'] ) );\n'
        '\t\t}')
assert src2.count(old5) == 1, 'M1 motif: %d' % src2.count(old5)
src2 = src2.replace(old5, new5)

io.open(r'C:/Users/PC/AppData/Local/Temp/ecole-extraction/patched/MarkingPeriods.php', 'w', encoding='utf-8', newline='\n').write(src2)
print('MarkingPeriods.php : 1 correctif OK')
