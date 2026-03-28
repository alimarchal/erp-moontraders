https://moontraders.test/reports/investment-summary?date=2026-03-24&supplier_id=3&designation=Salesman
is report Claim Amount, aur Ledger Amount srf posted ko filter krna hai agar us din do amount hain tou closing balance posted amount ka batana hai nh k us ka joh post nhi hui

aur next task yeh hai k stock amount correct snapshot ho rhe ho sab historical data sab ka thek ho kse bhi date mein yeh check krna hai

us k bad important
SHAHZAIN TRADERS INVESTMENT is table mein Total: ka jagha yeh lekhah hua ana chieyay

Total Main Investment as on yeh (Powder Expiry + Liquid Expiry + Claim Amount + Stock Amount + Credit Amount + Ledger Amount) aur make sure sab figures up to 2 decimal palces honay chieya properly formated use font-mono for numbers
phr us k bad yeh ana chieyay next row mein
Daily Cash as on OrignalDateOfThatDay yeh salesmane ke cash hai is k liyay yeh report banai hui hai you can understand that report https://moontraders.test/reports/cash-detail is ko sae in depth deep thinkgin kr k check krna yeh settlement sa joh daily cash hai wou hai make sure it matched with that date and historically bhi correct hone chieyay

phr Total Investment as on OrignalDateOfThatDay: is mein hona chieyay joh sum ho gah daily cash + Total Main Investment
phr us ka bad Total Main Investment as on Previous Day Date yeh gramer aur sentences sab joh oper hain sae lekhna as on aur previous date ka ta k professional lagay

phr us k bad yeh row rakhna hai Bank Online as on OrignalDateOfThatDay: yeh tun my dekhna hai us din ke date ledger register ke reports/ledger-register us din ke Online Amount of that company yad rakhna is mein joh supplier company ho ge us ke dekhna hai agar nestle ka hai nestle ka ledger ke Online Amount

phr yeh rakhna Increase in Investment as on OrignalDateOfThatDay: (Total Investment as on OrignalDateOfThatDay - Total Main Investment as on Previous Day - Bank Online as on OrignalDateOfThatDay

phr is table SHAHZAIN TRADERS INVESTMENT kay right side par yeh new table banawo filhal in mein data 0 rakho bad mein btwo gah is mein kai data rakhna hai

Expenses Detail
Stationary: 0.00
TCS: 0.00
Tonner & IT: 0.00
Salaries: 0.00
Fuel: 0.00
Van Work: 0.00
Total Expenses: 0.00

phr us ka bad yeh tables banana hai

Bank Opening Amount: filhal 0 rakho hard coded using variable bad mein btwo gah
Total Cash Received in Current Month: Yeh dekh lo cash waly ko ta k sae sa data rakhna ta k pata chalay yeh sae hai make sure deep think
Total Bank Amount: Total Cash Received in Current Month + Bank Opening Amount
Total Online Amount in Current Month: yeh leger sa dekhna us company ko ktne online amount dee hai us month
Closing Balance before Expenses: yeh Total Bank Amount - Total Online Amount in Current Month yeh ho gah
Total Expenses in Current Month: yeh joh oper table dia hai us ka sum ho gah Total Expenses
Closing Balance After Expenses: yeh Closing Balance before Expenses - Total Expenses in Current Month yeh ho gah

Last Month Main Investment: yeh calcualted hai dkeh lo
Curent Month Main Investment: yeh calcualted hai dkeh lo
Net Investment: Closing Balance before Expenses - Curent Month Main Investment
Increase In Investment Current Month: Last Month Main Investment - Net Investment
