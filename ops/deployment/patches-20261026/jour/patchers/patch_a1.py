import io

src = io.open(r'C:/Users/PC/AppData/Local/Temp/users-extraction/AddStudents.php', encoding='utf-8').read()

# A1 — garde ecole sur le retrait (miroir du correctif #376 de l'ajout)
old = ('\tif ( DeletePrompt( _( \'student from that user\' ), _( \'remove access to\' ) )\n'
       '\t\t&& ! empty( $_REQUEST[\'student_id_remove\'] ) )\n'
       '\t{\n'
       '\t\tDBQuery( "DELETE FROM students_join_users\n'
       '\t\t\tWHERE STUDENT_ID=\'" . (int) $_REQUEST[\'student_id_remove\'] . "\'\n'
       '\t\t\tAND STAFF_ID=\'" . UserStaffID() . "\'" );\n'
       '\n'
       '\t\t// Hook.\n'
       '\t\tdo_action( \'Users/AddStudents.php|user_unassign_role\' );\n'
       '\n'
       '\t\t// Unset modfunc & student ID remove & redirect URL.\n'
       '\t\tRedirectURL( [ \'modfunc\', \'student_id_remove\' ] );\n'
       '\t}')

new = ('\tif ( DeletePrompt( _( \'student from that user\' ), _( \'remove access to\' ) )\n'
       '\t\t&& ! empty( $_REQUEST[\'student_id_remove\'] ) )\n'
       '\t{\n'
       '\t\t// SchoolSafe A1 : meme garde ecole que le correctif #376 de l\'ajout —\n'
       '\t\t// ne pas retirer un eleve hors du perimetre des ecoles du compte.\n'
       '\t\t$remove_id = (int) $_REQUEST[\'student_id_remove\'];\n'
       '\n'
       '\t\tif ( User( \'SCHOOLS\' ) )\n'
       '\t\t{\n'
       '\t\t\t$remove_allowed = (bool) DBGetOne( "SELECT 1\n'
       '\t\t\t\tFROM student_enrollment\n'
       '\t\t\t\tWHERE SYEAR=\'" . UserSyear() . "\'\n'
       '\t\t\t\tAND SCHOOL_ID IN(" . mb_substr( str_replace( \',\', "\',\'", User( \'SCHOOLS\' ) ), 2, -2 ) . ")\n'
       '\t\t\t\tAND STUDENT_ID=\'" . $remove_id . "\'" );\n'
       '\t\t}\n'
       '\t\telse\n'
       '\t\t{\n'
       '\t\t\t$remove_allowed = true;\n'
       '\t\t}\n'
       '\n'
       '\t\tif ( $remove_allowed )\n'
       '\t\t{\n'
       '\t\t\tDBQuery( "DELETE FROM students_join_users\n'
       '\t\t\t\tWHERE STUDENT_ID=\'" . $remove_id . "\'\n'
       '\t\t\t\tAND STAFF_ID=\'" . UserStaffID() . "\'" );\n'
       '\n'
       '\t\t\t// Hook.\n'
       '\t\t\tdo_action( \'Users/AddStudents.php|user_unassign_role\' );\n'
       '\t\t}\n'
       '\n'
       '\t\t// Unset modfunc & student ID remove & redirect URL.\n'
       '\t\tRedirectURL( [ \'modfunc\', \'student_id_remove\' ] );\n'
       '\t}')

assert src.count(old) == 1, 'A1 motif: %d' % src.count(old)
io.open(r'C:/Users/PC/AppData/Local/Temp/users-extraction/patched/AddStudents.php', 'w', encoding='utf-8', newline='\n').write(src.replace(old, new))
print('A1 OK')
