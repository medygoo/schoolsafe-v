import io

# Applique A2 (bandeau parent sélectionné) + A5 (confirmation nominative)
# sur le fichier DÉJÀ patché A1 (users-extraction/patched/AddStudents.php)
p = r'C:/Users/PC/AppData/Local/Temp/users-extraction/patched/AddStudents.php'
src = io.open(p, encoding='utf-8').read()

# ---------- A2 : bandeau (bloc à 1 tab, contenu à 2 tabs) ----------
old_a2 = '''\tif ( UserStaffID() )
\t{
\t\tif ( $_REQUEST['search_modfunc'] === 'list' )'''

new_a2 = '''\tif ( UserStaffID() )
\t{
\t\t// SchoolSafe A2 : rappel visuel du parent selectionne — evite de rattacher au mauvais.
\t\t$selected_parent = DBGet( "SELECT FIRST_NAME,LAST_NAME,USERNAME
\t\t\tFROM staff
\t\t\tWHERE STAFF_ID='" . UserStaffID() . "'" );

\t\tif ( $selected_parent )
\t\t{
\t\t\t$selected_parent = $selected_parent[1];

\t\t\t$parent_role = ! empty( $selected_parent['USERNAME'] ) ?
\t\t\t\tsprintf( SchoolSafeT( 'Main parent (account: %s)', 'Parent principal (compte : %s)' ), $selected_parent['USERNAME'] ) :
\t\t\t\tSchoolSafeT( 'Guardian (no application account)', 'Gardien (sans compte d’application)' );

\t\t\techo '<div style="margin:10px 0;padding:10px 14px;border-left:4px solid #176b52;background:#f4faf7;border-radius:4px;">
\t\t\t\t<strong>' . AttrEscape( trim( (string) $selected_parent['FIRST_NAME'] . ' ' . $selected_parent['LAST_NAME'] ) ) . '</strong>
\t\t\t\t<span style="color:#5b6b64;"> — ' . AttrEscape( $parent_role ) . '</span>
\t\t\t</div>';
\t\t}

\t\tif ( $_REQUEST['search_modfunc'] === 'list' )'''

assert src.count(old_a2) == 1, 'A2 motif: %d' % src.count(old_a2)
src = src.replace(old_a2, new_a2)

# ---------- A5 : note nominative (bloc à col 0, contenu à 1 tab) ----------
old_a5 = '''if ( $_REQUEST['modfunc'] === 'delete'
\t&& AllowEdit()
\t&& UserStaffID() )
{
\tif ( DeletePrompt( _( 'student from that user' ), _( 'remove access to' ) )'''

new_a5 = '''if ( $_REQUEST['modfunc'] === 'delete'
\t&& AllowEdit()
\t&& UserStaffID() )
{
\t// SchoolSafe A5 : nommer l'eleve concerne et rappeler l'impact avant confirmation.
\t$remove_student_name = '';

\tif ( ! empty( $_REQUEST['student_id_remove'] ) )
\t{
\t\t$remove_student_name = (string) DBGetOne( "SELECT " . DisplayNameSQL( 's' ) . "
\t\t\tFROM students s
\t\t\tWHERE s.STUDENT_ID='" . (int) $_REQUEST['student_id_remove'] . "'" );
\t}

\techo ErrorMessage( [ SchoolSafeT(
\t\t'You are about to change the access of: ',
\t\t'Vous vous apprêtez à modifier l’accès de : '
\t) . AttrEscape( $remove_student_name ) . SchoolSafeT(
\t\t'. Confirming will decide whether this parent keeps seeing this student and receiving their scan notifications.',
\t\t'. La confirmation décidera si ce parent continue de voir cet élève et de recevoir ses notifications de scan.'
\t) ], 'note' );

\tif ( DeletePrompt( _( 'student from that user' ), _( 'remove access to' ) )'''

assert src.count(old_a5) == 1, 'A5 motif: %d' % src.count(old_a5)
src = src.replace(old_a5, new_a5)

io.open(p, 'w', encoding='utf-8', newline='\n').write(src)
print('A2 + A5 OK sur la base A1')
