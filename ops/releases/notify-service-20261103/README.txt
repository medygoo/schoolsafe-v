# Arret / reprise du distributeur
systemctl disable --now schoolsafe-notify-dispatch.timer   # arret
systemctl enable --now schoolsafe-notify-dispatch.timer    # reprise
journalctl -u schoolsafe-notify-dispatch.service -f        # journal
