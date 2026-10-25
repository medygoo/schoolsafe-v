<?php
require_once __DIR__ . '/includes/bridge.php';
add_action('Students/Student.php|create_student', 'SchoolSafeMatriculeCreated');
add_action('Warehouse.php|footer', 'SchoolSafeMatriculeRetry');

function SchoolSafeMatriculeCreated()
{
    try {
        $input = SchoolSafeMatriculeRead(UserStudentID());
        $queue = SchoolSafeMatriculeQueue();
        $statement = $queue->prepare('INSERT OR IGNORE INTO pending (student_id, school_id, year, actor_id) VALUES (?, ?, ?, ?)');
        $statement->execute([(string) $input['student']['STUDENT_ID'], (string) $input['enrollment']['SCHOOL_ID'], (string) $input['enrollment']['SYEAR'], (string) User('STAFF_ID')]);
        SchoolSafeMatriculeRetry();
    } catch (Throwable $error) {
        error_log('SchoolSafe matricule: preparation pending; inspect private bridge health.');
    }
}

function SchoolSafeMatriculeRetry()
{
    if (!User('STAFF_ID')) return;
    try {
        $queue = SchoolSafeMatriculeQueue();
        $statement = $queue->prepare('SELECT * FROM pending WHERE status = ? AND actor_id = ? AND school_id = ? AND year = ? LIMIT 5');
        $statement->execute(['pending', (string) User('STAFF_ID'), (string) UserSchool(), (string) UserSyear()]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $job) {
            try {
                $input = SchoolSafeMatriculeRead($job['student_id']);
                if ((string) $input['enrollment']['SCHOOL_ID'] !== $job['school_id'] || (string) $input['enrollment']['SYEAR'] !== $job['year']) continue;
                $matricule = SchoolSafeMatriculeAllocate($input);
                $done = $queue->prepare('UPDATE pending SET status = ?, matricule = ?, last_error = NULL WHERE student_id = ? AND school_id = ? AND year = ?');
                $done->execute(['ready', $matricule, $job['student_id'], $job['school_id'], $job['year']]);
            } catch (Throwable $error) {
                $failed = $queue->prepare('UPDATE pending SET attempts = attempts + 1, last_error = ? WHERE student_id = ? AND school_id = ? AND year = ?');
                $failed->execute(['PREPARATION_FAILED', $job['student_id'], $job['school_id'], $job['year']]);
            }
        }
    } catch (Throwable $error) {
        error_log('SchoolSafe matricule: retry unavailable; no native inscription changed.');
    }
}
