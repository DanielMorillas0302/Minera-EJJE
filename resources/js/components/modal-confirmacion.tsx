import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ModalConfirmacionProps {
    abierto: boolean;
    onCerrar: () => void;
    onConfirmar: () => void;
    titulo: string;
    descripcion: string;
    textoConfirmar?: string;
    variante?: 'destructive' | 'default';
    procesando?: boolean;
}

export function ModalConfirmacion({
    abierto,
    onCerrar,
    onConfirmar,
    titulo,
    descripcion,
    textoConfirmar = 'Confirmar',
    variante = 'destructive',
    procesando = false,
}: ModalConfirmacionProps) {
    return (
        <Dialog open={abierto} onOpenChange={(open) => !open && onCerrar()}>
            <DialogContent className="border-zinc-800 bg-[#0f0f13] text-zinc-100 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-zinc-100 text-base">{titulo}</DialogTitle>
                    <DialogDescription className="text-zinc-400 text-[13px]">{descripcion}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCerrar}
                        disabled={procesando}
                        className="border-zinc-800 bg-zinc-900 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 text-[13px]"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant={variante}
                        onClick={onConfirmar}
                        disabled={procesando}
                        className={
                            variante === 'destructive'
                                ? 'bg-red-600 text-white hover:bg-red-500 text-[13px]'
                                : 'bg-zinc-100 text-zinc-900 hover:bg-white text-[13px]'
                        }
                    >
                        {procesando ? 'Procesando...' : textoConfirmar}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
