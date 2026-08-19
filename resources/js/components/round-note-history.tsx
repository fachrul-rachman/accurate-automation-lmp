export type RoundNote = {
    id: number;
    note: string;
    event: string;
    user_name: string | null;
    role: string | null;
    item_names: string[];
    created_at: string | null;
};

export function RoundNoteHistory({ notes }: { notes: RoundNote[] }) {
    if (notes.length === 0) {
        return null;
    }

    return (
        <section className="mt-5 rounded-lg border border-slate-200 bg-white p-5">
            <h2 className="text-base font-semibold">Riwayat Catatan</h2>
            <div className="mt-3 space-y-3">
                {notes.map((note) => (
                    <div
                        key={note.id}
                        className="rounded-md bg-slate-50 p-3 text-sm"
                    >
                        <div className="flex flex-wrap justify-between gap-2 text-slate-500">
                            <span>
                                {note.user_name ?? '-'} ({note.role ?? '-'})
                            </span>
                            <span>{note.created_at ?? '-'}</span>
                        </div>
                        {note.item_names.length > 0 && (
                            <p className="mt-1 font-medium">
                                Item: {note.item_names.join(', ')}
                            </p>
                        )}
                        <p className="mt-1">{note.note}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}
