import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Login" />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950">
                <section className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-md flex-col justify-center">
                    <Link
                        href="/"
                        className="mb-8 text-sm font-semibold text-teal-700"
                    >
                        Accurate Check
                    </Link>
                    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        <h1 className="text-xl font-semibold">Masuk</h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Gunakan akun yang sudah dikonfigurasi untuk workflow
                            penerimaan barang.
                        </p>

                        <form className="mt-6 space-y-4" onSubmit={submit}>
                            <label className="block">
                                <span className="text-sm font-medium text-slate-800">
                                    Email
                                </span>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-base outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                    autoComplete="email"
                                    required
                                />
                                {errors.email && (
                                    <span className="mt-1 block text-sm text-red-600">
                                        {errors.email}
                                    </span>
                                )}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-slate-800">
                                    Password
                                </span>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData('password', event.target.value)
                                    }
                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-base outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <span className="mt-1 block text-sm text-red-600">
                                        {errors.password}
                                    </span>
                                )}
                            </label>

                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) =>
                                        setData(
                                            'remember',
                                            event.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-slate-300 text-teal-700"
                                />
                                Ingat sesi saya
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                Masuk
                            </button>
                        </form>
                    </div>
                </section>
            </main>
        </>
    );
}
