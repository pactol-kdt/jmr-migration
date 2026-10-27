import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@/inertia';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-8">
                <h1 className="text-2xl font-semibold tracking-tight text-ink">
                    Reset password
                </h1>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="rounded-2xl border border-slate-200/80 bg-panel p-6 shadow-[0_1px_2px_rgba(27,58,95,0.05)] sm:p-8">
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-6">
                    <PrimaryButton className="w-full" disabled={processing}>
                        Email reset link
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
