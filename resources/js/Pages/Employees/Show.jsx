import RecordsIndex from '@/Pages/Records/Index';

export default function Show({ employee, ...props }) {
    const profile = [
        ['Username', employee.username],
        ['Group', employee.group_name],
        ['Designation', employee.designation],
        ['Role', employee.role],
        employee.directory_active === false
            ? ['Status', employee.resigned_on ? `Resigned ${employee.resigned_on}` : 'Resigned']
            : employee.directory_active === null
              ? ['Status', 'Information was not updated. This employee is not in the current directory, so the 2017 details were kept.']
              : null,
    ].filter(Boolean)
        .filter(([, value]) => value)
        .map(([label, value]) => ({ label, value }));

    return (
        <RecordsIndex
            {...props}
            browseRoute="employees.show"
            browseParams={{ employee: employee.employee_id }}
            title={employee.name || `Employee ${employee.employee_id}`}
            profile={profile}
            hideEmployeeFilter
            hideEmployeeColumn
            statusEmployeeId={employee.employee_id}
            backLink={{
                href: route('employees.index'),
                label: 'Back to employees',
            }}
        />
    );
}
