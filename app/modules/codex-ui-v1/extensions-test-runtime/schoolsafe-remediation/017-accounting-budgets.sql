-- SchoolSafe — budget prévisionnel par catégorie comptable.
--
-- Additif, idempotent. Ne modifie aucune table native.
--
-- `ProgramFunctions/SchoolSafeBudgetPayroll.fnc.php` et
-- `modules/Accounting/Categories.php` lisent déjà cette table, mais aucun
-- script du dépôt ne la créait : elle n'existait que dans les bases créées à
-- la main, si bien que le tableau de bord et la page Catégories échouaient sur
-- un déploiement neuf (relation "schoolsafe_accounting_budgets" does not exist).
--
-- Clé = (école, année scolaire, catégorie) : un seul budget par catégorie et
-- par exercice, réécrit à chaque saisie. Le montant prévu est comparé aux
-- paiements réels de `accounting_payments`.

CREATE TABLE IF NOT EXISTS schoolsafe_accounting_budgets (
    school_id integer NOT NULL,
    syear numeric NOT NULL,
    category_id integer NOT NULL,
    amount numeric(14,2) NOT NULL,
    updated_by integer,
    updated_at timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT schoolsafe_accounting_budgets_amount_check
        CHECK (amount >= 0)
);

CREATE INDEX IF NOT EXISTS schoolsafe_accounting_budgets_category_idx
    ON schoolsafe_accounting_budgets (category_id);

-- Contraintes nommées ajoutées seulement si elles manquent (PostgreSQL ne
-- connaît pas « ADD CONSTRAINT IF NOT EXISTS »).
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'schoolsafe_accounting_budgets_pkey'
    ) THEN
        ALTER TABLE schoolsafe_accounting_budgets
            ADD CONSTRAINT schoolsafe_accounting_budgets_pkey
            PRIMARY KEY (school_id, syear, category_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'schoolsafe_accounting_budgets_school_id_syear_fkey'
    ) THEN
        ALTER TABLE schoolsafe_accounting_budgets
            ADD CONSTRAINT schoolsafe_accounting_budgets_school_id_syear_fkey
            FOREIGN KEY (school_id, syear) REFERENCES schools (id, syear)
            ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'schoolsafe_accounting_budgets_category_id_fkey'
    ) THEN
        ALTER TABLE schoolsafe_accounting_budgets
            ADD CONSTRAINT schoolsafe_accounting_budgets_category_id_fkey
            FOREIGN KEY (category_id) REFERENCES accounting_categories (id)
            ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'schoolsafe_accounting_budgets_updated_by_fkey'
    ) THEN
        ALTER TABLE schoolsafe_accounting_budgets
            ADD CONSTRAINT schoolsafe_accounting_budgets_updated_by_fkey
            FOREIGN KEY (updated_by) REFERENCES staff (staff_id)
            ON DELETE SET NULL;
    END IF;
END
$$;

-- Rôle applicatif : mêmes droits que les autres tables SchoolSafe.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'schoolsafe_app') THEN
        GRANT SELECT, INSERT, UPDATE, DELETE ON schoolsafe_accounting_budgets
            TO schoolsafe_app;
    END IF;
END
$$;
