Ek new report banana hai joh is tra ke ho ge '/Users/alirazamarhcal/Desktop/Screenshot 2026-03-26 at 10.09.11 PM.png'
tum mre is report k filters ko bhi dekhna https://moontraders.test/reports/creditors-ledger aur is mein zada sa zad filters honay chieyay ta k maximum filters apply kr sko.
Yeh report supplier wise ho ge aur customer employee account ko use krna aur salesman k related ho ge by default nestle ke show krne hai aur designation salesman ho
is report ko for reference dekho designing ko https://moontraders.test/reports/credit-sales/salesman-history
salesman name salesman ka name hai salesman designation hai aur humary system mein employee hai jis ka designation salesman hai supplier wise
Sr#, Salesmane Name, Opening Credit (Us date tak kia tha), Credit (us din kia dia), Recovery (us din kia hui), Total Credit,

last par SHAHZAIN TRADERS INVESTMENT hai lekha hua us mein
powder expiry hai joh wou sales_settlement_amr_powders tables sa a rhe hai us table mein ek new column add kro new migration aur make sure wou column jab update krin update ho jayay aur liquid expiry wou hai joh sales_settlement_amr_liquids is table mein ho ge us date tak aur in dono tables mein 1 column add krna is_disposed ka by default false ho gah aur tum ny is report mein dekhna hai wou sab
sales_settlement_amr_liquids ka amount ka sum
and sales_settlement_amr_liquids amoutn ka sum
jis ka is_disposed false ho gah
us ka sum Powder Expiry mein ayay gah sales_settlement_amr_powders jis ka jis ka is_disposed false ho gah
Liquid Expiry mein sales_settlement_amr_liquids ke amount ka sum ayay gah jis ka jis ka is_disposed false ho gah

Claim Amount sum ho gah last figure claim_registers ke last entry ka supplier_id wise joh is mein selected ho ge like nestle hai tou nestle ke last amount jsay is report mein https://moontraders.test/reports/claim-register closing balance ho geh us supplier ka last joh posted ho geh posted_at for reference check this
'/Users/alirazamarhcal/Desktop/Screenshot 2026-03-26 at 10.25.59 PM.png'
aur Stock Amount wou ho ge joh humary pas us supplier ke inventory ho ge joh cost Unit Cost ka sum ho gah i mean inventoy current_stock_by_batch mein aur current_stock mein joh total value ho ge is mein khod dekhna joh best suit krna hai from my prospective current_stock_by_batch is better option but query bht heavey nh ho is ko tum ny khod dekhna hai sab tables ko jaha sa best option mily mujy batana play puchna
phr Credit Amount hai yeh us supplier ke sab salesman ke closing amount hai joh oper report mein ho ge
thek ise he tra Ledger Amount leger_registers ka table sa ata hai supplier_id wise joh ho ge is ke report be banai hai
https://moontraders.test/reports/leger-register yeh bhi closing balance show krna hai last is mein posted ka sceen nhi hai is tra yeh over all phr total ho gah

is mein total k bad previous date ka ka total hona chieyay aur phr difference in percentage annd amount ta k pata chalay pachlay din ktna tha ab ktna hai

is report ko Cash Collection Detail k bad link mein dalna aur related permission bhi add krna https://moontraders.test/reports
Receivables & Core Reports is mein
