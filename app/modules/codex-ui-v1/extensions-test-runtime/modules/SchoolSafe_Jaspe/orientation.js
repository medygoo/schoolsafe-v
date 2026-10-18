(function (root) {
    'use strict';
    var destinations = {
        dashboard: 'Modules.php?modname=misc/Portal.php',
        student_register: 'Modules.php?modname=Students/Student.php&include=General_Info&student_id=new',
        school_configuration: 'Modules.php?modname=School_Setup/Configuration.php',
        teacher_assignments: 'Modules.php?modname=Grades/Assignments.php',
        teacher_grades: 'Modules.php?modname=Grades/Grades.php'
    };
    function resolve(text, actions) {
        if (typeof text !== 'string' || text.length > 2000 || !Array.isArray(actions)) return null;
        var query = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        var ids = [];
        if (/\b(inscri\w*|enregistr\w*|ajout\w*)\b/.test(query) && /\b(enfant|eleve)\b/.test(query)) ids.push('student_register');
        if (/\b(tableau de bord|accueil)\b/.test(query)) ids.push('dashboard');
        if (/\b(configuration|parametrage)\b/.test(query)) ids.push('school_configuration');
        if (/\b(devoir|devoirs|interrogation|examen)\b/.test(query)) ids.push('teacher_assignments');
        if (/\b(carnet de notes|cotes|notes)\b/.test(query)) ids.push('teacher_grades');
        if (ids.length !== 1) return null;
        return actions.find(function (action) {
            return action && action.id === ids[0] && action.kind === 'navigation'
                && action.url === destinations[ids[0]];
        }) || null;
    }
    var api = { resolve: resolve };
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.SchoolSafeJaspeOrientation = api;
})(typeof window === 'object' ? window : globalThis);
