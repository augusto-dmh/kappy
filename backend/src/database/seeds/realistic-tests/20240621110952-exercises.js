"use strict";

module.exports = {
  up: async (queryInterface, Sequelize) => {
    const exercises = [
      {
        id: 1,
        lesson_id: 1,
        question:
          "Qual das seguintes opções declara corretamente uma variável inteira em Java?",
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 2,
        lesson_id: 1,
        question:
          "Qual das seguintes linhas de código atribui corretamente o valor 10 à variável x?",
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 3,
        lesson_id: 1,
        question:
          "Qual tipo de dado em Java é usado para armazenar um número de ponto flutuante?",
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 4,
        lesson_id: 1,
        question:
          "Qual das seguintes opções é a maneira correta de iniciar um método main em Java?",
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 5,
        lesson_id: 1,
        question: `
      Qual será o valor da variável resultado após a execução do seguinte código?
      int a = 5;
      int b = 3;
      int resultado = a + b;
      `,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 6,
        lesson_id: 2,
        question: `
      Qual será a saída do seguinte código?
      int a = 10;
      int b = 20;
      boolean resultado = (a < b);
      System.out.println(resultado);
      `,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 7,
        lesson_id: 2,
        question: `
      Considere o código abaixo. Qual será a saída?
      int x = 15;
      int y = 15;
      boolean resultado = (x == y);
      System.out.println(resultado);
      `,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 8,
        lesson_id: 2,
        question: `
      Qual será a saída do seguinte código?
      String str1 = "Hello";
      String str2 = "hello";
      boolean resultado = str1.equals(str2);
      System.out.println(resultado);
      `,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 9,
        lesson_id: 2,
        question: `
        Considere o código abaixo. Qual será a saída?
        int a = 5;
        int b = 3;
        boolean resultado = (a > b);
        System.out.println(resultado);
        `,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 10,
        lesson_id: 2,
        question: `
        Qual será a saída do seguinte código?
        String str1 = "Java";
        String str2 = "Java";
        boolean resultado = (str1 != str2);
        System.out.println(resultado);
        `,
        created_at: new Date(),
        updated_at: new Date(),
      },
    ];

    await queryInterface.bulkInsert("exercises", exercises, {});
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.bulkDelete("exercises", null, {});
  },
};
